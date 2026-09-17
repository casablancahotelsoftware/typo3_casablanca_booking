<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Model\Rate;
use Casablanca\CasablancaBooking\Domain\Repository\AvailabilityRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use Casablanca\CasablancaBooking\Service\Api\ApiClientFactory;
use Casablanca\CasablancaBooking\Service\BookingOffer\BookingOffersFetchService;
use Casablanca\CasablancaBooking\Service\Calendar\CalendarFetchService;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Package (Pauschale) cards with overview/detail modes.
 */
class PackagesController extends AbstractWidgetController
{
    use CalendarApiActionsTrait;
    use CalendarPresentationTrait;
    use DetailCalendarTrait;

    private const DISPLAY_MODE_OVERVIEW = 'overview';
    private const DISPLAY_MODE_DETAIL = 'detail';
    private const LINK_TYPE_BOOK = 'book';
    private const LINK_TYPE_DETAILS = 'details';

    /** @var AvailabilityRepository */
    private $availabilityRepository;

    /** @var RateRepository */
    private $rateRepository;

    /** @var CalendarFetchService */
    protected $calendarFetchService;

    /** @var BookingOffersFetchService */
    protected $bookingOffersFetchService;

    public function __construct(
        SiteFinder $siteFinder,
        ApiClientFactory $apiClientFactory,
        IbeUrlBuilder $ibeUrlBuilder,
        AvailabilityRepository $availabilityRepository,
        RateRepository $rateRepository,
        CalendarFetchService $calendarFetchService,
        BookingOffersFetchService $bookingOffersFetchService
    ) {
        parent::__construct($siteFinder, $apiClientFactory, $ibeUrlBuilder);
        $this->availabilityRepository = $availabilityRepository;
        $this->rateRepository = $rateRepository;
        $this->calendarFetchService = $calendarFetchService;
        $this->bookingOffersFetchService = $bookingOffersFetchService;
    }

    /**
     * @return mixed
     */
    public function showAction(string $packageSlug = '')
    {
        $this->includeFrontendAssets();

        $config = $this->resolveConfig();
        if ($config === null) {
            $this->view->assign('error', 'not_configured');

            return $this->htmlResponseOrNull();
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $displaySettings = $this->parseDisplaySettings($settings);
        $common = $this->parseCommonSettings($config);
        $stayNights = max(1, min(30, (int)($settings['stayNights'] ?? 7)));
        $filterByStayNights = !isset($settings['filterByStayNights'])
            || (int)$settings['filterByStayNights'] === 1;

        $from = new DateTimeImmutable('today');
        $until = $from->modify(sprintf('+%d days', $common['windowDays'] - 1));
        $departure = $from->modify(sprintf('+%d days', $stayNights));

        $resolvedPackageSlug = trim($packageSlug);
        if ($resolvedPackageSlug === '') {
            $resolvedPackageSlug = $this->resolvePackageSlugFromRequest();
        }

        $packages = $this->resolvePackages(
            $config,
            $settings,
            $displaySettings['displayMode'],
            $resolvedPackageSlug
        );

        if ($displaySettings['displayMode'] === self::DISPLAY_MODE_DETAIL && $packages === []) {
            $this->view->assign('error', 'package_not_found');
            $this->assignCommonViewVariables($config, $common, $displaySettings, $stayNights, $from, $until, []);

            return $this->htmlResponseOrNull();
        }

        if ($displaySettings['displayMode'] === self::DISPLAY_MODE_OVERVIEW && $filterByStayNights) {
            $packages = array_values(array_filter(
                $packages,
                function (Rate $package) use ($config, $from, $until, $stayNights): bool {
                    $bookableNights = $this->availabilityRepository->findBookablePackageNights(
                        $config->siteIdentifier,
                        $from,
                        $until,
                        $package->getRateId()
                    );

                    return $this->packageSupportsStayLength($bookableNights, $stayNights);
                }
            ));
        }

        $detailPageUid = $this->parseDetailPageUid($settings);
        $occupancy = $this->buildOccupancy(
            $common['adults'],
            $common['defaultChildrenCount'],
            $common['childrenAges']
        );

        $cards = [];
        foreach ($packages as $package) {
            $ibeUrl = $this->ibeUrlBuilder->build(
                $config,
                $from,
                $departure,
                $occupancy,
                null,
                $common['culture'],
                $package->getRateId()
            );

            $detailUrl = '';
            if (
                $displaySettings['displayMode'] === self::DISPLAY_MODE_OVERVIEW
                && $displaySettings['cardLinkType'] === self::LINK_TYPE_DETAILS
                && $detailPageUid > 0
                && $package->getSlug() !== ''
            ) {
                $detailUrl = $this->buildDetailUrl($detailPageUid, $package->getSlug());
            }

            $cheapest = $this->availabilityRepository->findCheapestPrice(
                $config->siteIdentifier,
                $from,
                $until,
                null,
                $package->getRateId()
            );

            $cards[] = [
                'package' => $package,
                'fromPrice' => $cheapest !== null ? ($cheapest['price'] ?? null) : null,
                'currency' => $cheapest !== null ? ($cheapest['currency'] ?? 'EUR') : 'EUR',
                'ibeUrl' => $ibeUrl,
                'detailUrl' => $detailUrl,
            ];
        }

        $this->tagPageCache($config);

        $this->assignCommonViewVariables(
            $config,
            $common,
            $displaySettings,
            $stayNights,
            $from,
            $until,
            $cards
        );

        if ($displaySettings['displayMode'] === self::DISPLAY_MODE_DETAIL) {
            $this->assignDetailCalendarVariables($config, $common, $settings, $cards, true);
        }

        return $this->htmlResponseOrNull();
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array{
     *     displayMode: string,
     *     showName: bool,
     *     showDescription: bool,
     *     showPrice: bool,
     *     showImage: bool,
     *     cardLinkType: string
     * }
     */
    private function parseDisplaySettings(array $settings): array
    {
        $displayMode = (string)($settings['displayMode'] ?? self::DISPLAY_MODE_OVERVIEW);
        if (!in_array($displayMode, [self::DISPLAY_MODE_OVERVIEW, self::DISPLAY_MODE_DETAIL], true)) {
            $displayMode = self::DISPLAY_MODE_OVERVIEW;
        }

        $cardLinkType = (string)($settings['cardLinkType'] ?? self::LINK_TYPE_BOOK);
        if (!in_array($cardLinkType, [self::LINK_TYPE_BOOK, self::LINK_TYPE_DETAILS], true)) {
            $cardLinkType = self::LINK_TYPE_BOOK;
        }

        return [
            'displayMode' => $displayMode,
            'showName' => $this->isSettingEnabled($settings, 'showName'),
            'showDescription' => $this->isSettingEnabled($settings, 'showDescription'),
            'showPrice' => $this->isSettingEnabled($settings, 'showPrice'),
            'showImage' => $this->isSettingEnabled($settings, 'showImage'),
            'cardLinkType' => $cardLinkType,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function isSettingEnabled(array $settings, string $key): bool
    {
        if (!array_key_exists($key, $settings)) {
            return true;
        }

        $value = $settings[$key];

        return $value !== '' && $value !== '0' && $value !== 0 && $value !== false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function parseDetailPageUid(array $settings): int
    {
        $raw = $settings['detailPageUid'] ?? '';
        if (is_array($raw)) {
            $raw = $raw[0] ?? '';
        }

        if (is_string($raw) && strpos($raw, ',') !== false) {
            $raw = explode(',', $raw)[0];
        }

        return max(0, (int)$raw);
    }

    private function resolvePackageSlugFromRequest(): string
    {
        if ($this->request->hasArgument('packageSlug')) {
            return trim((string)$this->request->getArgument('packageSlug'));
        }

        $pluginSignature = Typo3Adapter::getPluginSignature('CasablancaBooking', 'Packages');
        $params = (array)($this->request->getQueryParams()[$pluginSignature] ?? []);

        return trim((string)($params['packageSlug'] ?? ''));
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return Rate[]
     */
    private function resolvePackages(
        SiteConfigurationDto $config,
        array $settings,
        string $displayMode,
        string $packageSlug
    ): array {
        if ($displayMode === self::DISPLAY_MODE_DETAIL) {
            if ($packageSlug === '') {
                return [];
            }

            $package = $this->rateRepository->findOneBySiteAndSlug($config->siteIdentifier, $packageSlug);

            return $package !== null ? [$package] : [];
        }

        $packages = $this->rateRepository->findPackagesForSite($config->siteIdentifier);
        $filterRateId = trim((string)($settings['filterPackage'] ?? ''));
        if ($filterRateId === '') {
            return $packages;
        }

        return array_values(array_filter(
            $packages,
            static function (Rate $rate) use ($filterRateId): bool {
                return $rate->getRateId() === $filterRateId;
            }
        ));
    }

    private function buildDetailUrl(int $detailPageUid, string $packageSlug): string
    {
        if ($detailPageUid <= 0 || $packageSlug === '') {
            return '';
        }

        try {
            $url = $this->uriBuilder
                ->reset()
                ->setTargetPageUid($detailPageUid)
                ->setCreateAbsoluteUri(false)
                ->uriFor(
                    'show',
                    ['packageSlug' => $packageSlug],
                    'Packages',
                    'CasablancaBooking',
                    'Packages'
                );

            return $url !== '' ? $url : $this->buildDetailUrlFallback($detailPageUid, $packageSlug);
        } catch (\Throwable) {
            return $this->buildDetailUrlFallback($detailPageUid, $packageSlug);
        }
    }

    private function buildDetailUrlFallback(int $detailPageUid, string $packageSlug): string
    {
        $pluginSignature = Typo3Adapter::getPluginSignature('CasablancaBooking', 'Packages');

        return (string)$this->uriBuilder
            ->reset()
            ->setTargetPageUid($detailPageUid)
            ->setCreateAbsoluteUri(false)
            ->setArguments([
                $pluginSignature => [
                    'packageSlug' => $packageSlug,
                ],
            ])
            ->build();
    }

    /**
     * @param int[] $bookableNights
     */
    private function packageSupportsStayLength(array $bookableNights, int $stayNights): bool
    {
        if ($bookableNights === []) {
            return true;
        }

        foreach ($bookableNights as $nights) {
            if ((int)$nights === $stayNights) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $common
     * @param array<int, array<string, mixed>> $cards
     */
    private function assignCommonViewVariables(
        SiteConfigurationDto $config,
        array $common,
        array $displaySettings,
        int $stayNights,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        array $cards
    ): void {
        $this->view->assignMultiple([
            'config' => $config,
            'cards' => $cards,
            'culture' => $common['culture'],
            'defaultAdults' => $common['adults'],
            'defaultChildrenCount' => $common['defaultChildrenCount'],
            'windowDays' => $common['windowDays'],
            'from' => $from,
            'until' => $until,
            'displayMode' => $displaySettings['displayMode'],
            'showName' => $displaySettings['showName'],
            'showDescription' => $displaySettings['showDescription'],
            'showPrice' => $displaySettings['showPrice'],
            'showImage' => $displaySettings['showImage'],
            'cardLinkType' => $displaySettings['cardLinkType'],
            'stayNights' => $stayNights,
            'ibeLinkTarget' => $this->parseIbeLinkTarget(),
        ]);
    }
}
