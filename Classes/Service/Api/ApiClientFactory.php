<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use Casablanca\CasablancaBooking\Domain\Model\Configuration;
use Casablanca\CasablancaBooking\Domain\Repository\ConfigurationRepository;
use Casablanca\CasablancaBooking\Service\Api\Client\BookingOffersClient;
use Casablanca\CasablancaBooking\Service\Api\Client\CalendarDatesClient;
use Casablanca\CasablancaBooking\Service\Api\Client\InventoryCacheClient;
use Casablanca\CasablancaBooking\Service\Api\Client\RatesClient;
use Casablanca\CasablancaBooking\Service\Api\Client\RoomTypesClient;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Resolves credentials and builds API clients for a given TYPO3 Site.
 */
final class ApiClientFactory
{
    public const DEFAULT_API_BASE_URL = 'https://api.casablanca.at';
    public const DEFAULT_IBE_BASE_URL = 'https://ibe.casablanca.at';

    /** @var SiteFinder */
    private $siteFinder;

    /** @var ConfigurationRepository */
    private $configurationRepository;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var GuzzleClientFactory */
    private $guzzleClientFactory;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        SiteFinder $siteFinder,
        ConfigurationRepository $configurationRepository,
        RequestFactory $requestFactory,
        GuzzleClientFactory $guzzleClientFactory,
        LoggerInterface $logger
    ) {
        $this->siteFinder = $siteFinder;
        $this->configurationRepository = $configurationRepository;
        $this->requestFactory = $requestFactory;
        $this->guzzleClientFactory = $guzzleClientFactory;
        $this->logger = $logger;
    }

    public function resolveConfiguration(Site $site): ?SiteConfigurationDto
    {
        $yamlSettings = (array)($site->getConfiguration()['casablanca_booking'] ?? []);
        $dbConfig = $this->configurationRepository->findBySiteIdentifier($site->getIdentifier());

        if ($yamlSettings === [] && $dbConfig === null) {
            return null;
        }

        $settings = $this->mergeSettings($yamlSettings, $dbConfig);

        $tenantId = (string)($settings['tenantId'] ?? '');
        $ibeContextId = (string)($settings['urlFriendlyIbeContextId'] ?? '');

        $apiKey = $this->resolveApiKey($settings, $dbConfig);

        if ($tenantId === '' || $ibeContextId === '' || $apiKey === '') {
            throw new RuntimeException(sprintf(
                'CASABLANCA booking: site "%s" declares casablanca_booking config '
                . 'but is missing one of tenantId (%s), urlFriendlyIbeContextId (%s), '
                . 'or API key (configure in Tools → CASABLANCA Booking).',
                $site->getIdentifier(),
                $tenantId !== '' ? 'OK' : 'MISSING',
                $ibeContextId !== '' ? 'OK' : 'MISSING'
            ));
        }

        $defaultOccupancySettings = (array)($settings['defaultOccupancy'] ?? []);
        $defaultAdults = (int)($defaultOccupancySettings['numberOfAdults']
            ?? $settings['defaultAdults']
            ?? 2);
        $defaultChildren = (array)($defaultOccupancySettings['ageOfChildren']
            ?? $settings['defaultChildrenAges']
            ?? []);

        return new SiteConfigurationDto(
            $site->getIdentifier(),
            $tenantId,
            $ibeContextId,
            $apiKey,
            rtrim((string)($settings['apiBaseUrl'] ?? self::DEFAULT_API_BASE_URL), '/'),
            rtrim((string)($settings['ibeBaseUrl'] ?? self::DEFAULT_IBE_BASE_URL), '/'),
            (string)($settings['defaultCulture'] ?? 'de'),
            (int)($settings['syncRangeDays'] ?? 365),
            (int)($settings['syncChunkDays'] ?? 31),
            (int)($settings['paginationTop'] ?? 100),
            new RoomOccupancyDto(
                $defaultAdults,
                array_map('intval', $defaultChildren)
            ),
            trim((string)($settings['servicePath'] ?? 'ibe'), '/'),
            IbeLinkStyle::normalize((string)($settings['ibeLinkStyle'] ?? IbeLinkStyle::FULL_PATH))
        );
    }

    public function resolveBySiteIdentifier(string $siteIdentifier): ?SiteConfigurationDto
    {
        return $this->resolveConfiguration(
            $this->siteFinder->getSiteByIdentifier($siteIdentifier)
        );
    }

    public function createHttpClient(SiteConfigurationDto $config): CasablancaHttpClient
    {
        return new CasablancaHttpClient(
            $this->requestFactory,
            $this->guzzleClientFactory,
            $this->logger,
            $config
        );
    }

    public function createCalendarDatesClient(SiteConfigurationDto $config): CalendarDatesClient
    {
        return new CalendarDatesClient($this->createHttpClient($config), $config);
    }

    public function createInventoryCacheClient(SiteConfigurationDto $config): InventoryCacheClient
    {
        return new InventoryCacheClient($this->createHttpClient($config), $config);
    }

    public function createRoomTypesClient(SiteConfigurationDto $config): RoomTypesClient
    {
        return new RoomTypesClient($this->createHttpClient($config), $config);
    }

    public function createRatesClient(SiteConfigurationDto $config): RatesClient
    {
        return new RatesClient($this->createHttpClient($config), $config);
    }

    public function createBookingOffersClient(SiteConfigurationDto $config): BookingOffersClient
    {
        return new BookingOffersClient($this->createHttpClient($config), $config);
    }

    /**
     * @return array<string, SiteConfigurationDto>
     */
    public function getAllConfiguredSites(): array
    {
        $result = [];
        $seen = [];

        foreach ($this->siteFinder->getAllSites() as $site) {
            $config = $this->resolveConfiguration($site);
            if ($config !== null) {
                $result[$site->getIdentifier()] = $config;
                $seen[$site->getIdentifier()] = true;
            }
        }

        foreach ($this->configurationRepository->findAll() as $dbConfig) {
            $siteIdentifier = $dbConfig->getSiteIdentifier();
            if ($siteIdentifier === '' || isset($seen[$siteIdentifier])) {
                continue;
            }

            try {
                $config = $this->resolveBySiteIdentifier($siteIdentifier);
                if ($config !== null) {
                    $result[$siteIdentifier] = $config;
                }
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Skipping CASABLANCA site from DB config: {site} — {error}',
                    ['site' => $siteIdentifier, 'error' => $e->getMessage()]
                );
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $yamlSettings
     * @return array<string, mixed>
     */
    private function mergeSettings(array $yamlSettings, ?Configuration $dbConfig): array
    {
        if ($dbConfig === null) {
            return $yamlSettings;
        }

        $merged = $yamlSettings;

        if ($dbConfig->getTenantId() !== '') {
            $merged['tenantId'] = $dbConfig->getTenantId();
        }
        if ($dbConfig->getUrlFriendlyIbeContextId() !== '') {
            $merged['urlFriendlyIbeContextId'] = $dbConfig->getUrlFriendlyIbeContextId();
        }
        if ($dbConfig->getApiBaseUrl() !== '') {
            $merged['apiBaseUrl'] = $dbConfig->getApiBaseUrl();
        }
        if ($dbConfig->getIbeBaseUrl() !== '') {
            $merged['ibeBaseUrl'] = $dbConfig->getIbeBaseUrl();
        }
        if ($dbConfig->getIbeLinkStyle() !== '') {
            $merged['ibeLinkStyle'] = $dbConfig->getIbeLinkStyle();
        }
        if ($dbConfig->getServicePath() !== '') {
            $merged['servicePath'] = $dbConfig->getServicePath();
        }
        if ($dbConfig->getDefaultCulture() !== '') {
            $merged['defaultCulture'] = $dbConfig->getDefaultCulture();
        }
        if ($dbConfig->getSyncRangeDays() > 0) {
            $merged['syncRangeDays'] = $dbConfig->getSyncRangeDays();
        }
        if ($dbConfig->getSyncChunkDays() > 0) {
            $merged['syncChunkDays'] = $dbConfig->getSyncChunkDays();
        }
        if ($dbConfig->getPaginationTop() > 0) {
            $merged['paginationTop'] = $dbConfig->getPaginationTop();
        }
        if ($dbConfig->getDefaultAdults() > 0) {
            $merged['defaultAdults'] = $dbConfig->getDefaultAdults();
        }
        if ($dbConfig->getDefaultChildrenAges() !== []) {
            $merged['defaultChildrenAges'] = $dbConfig->getDefaultChildrenAges();
        }

        if ($dbConfig->getApiKeyEncrypted() !== '') {
            $merged['apiKeyEncrypted'] = $dbConfig->getApiKeyEncrypted();
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolveApiKey(array $settings, ?Configuration $dbConfig): string
    {
        $encrypted = (string)($settings['apiKeyEncrypted'] ?? '');
        if ($encrypted === '' && $dbConfig !== null) {
            $encrypted = $dbConfig->getApiKeyEncrypted();
        }
        if ($encrypted === '') {
            return '';
        }

        return $this->configurationRepository->decryptApiKey($encrypted);
    }
}
