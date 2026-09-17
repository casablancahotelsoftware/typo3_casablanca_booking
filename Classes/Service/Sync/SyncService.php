<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Model\SyncLog;
use Casablanca\CasablancaBooking\Domain\Repository\ConfigurationRepository;
use Casablanca\CasablancaBooking\Domain\Repository\SyncLogRepository;
use Casablanca\CasablancaBooking\Install\AvailabilitySchemaMigrator;
use Casablanca\CasablancaBooking\Service\Api\ApiClientFactory;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Orchestrates price and catalog synchronisation for one or more TYPO3 sites.
 */
final class SyncService
{
    /** @var ApiClientFactory */
    private $apiClientFactory;

    /** @var PriceNormaliser */
    private $priceNormaliser;

    /** @var AvailabilityWriter */
    private $availabilityWriter;

    /** @var AvailabilitySchemaMigrator */
    private $schemaMigrator;

    /** @var RoomTypeWriter */
    private $roomTypeWriter;

    /** @var RateWriter */
    private $rateWriter;

    /** @var CacheTagFlusher */
    private $cacheTagFlusher;

    /** @var SyncLogRepository */
    private $syncLogRepository;

    /** @var ConfigurationRepository */
    private $configurationRepository;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ApiClientFactory $apiClientFactory,
        PriceNormaliser $priceNormaliser,
        AvailabilityWriter $availabilityWriter,
        AvailabilitySchemaMigrator $schemaMigrator,
        RoomTypeWriter $roomTypeWriter,
        RateWriter $rateWriter,
        CacheTagFlusher $cacheTagFlusher,
        SyncLogRepository $syncLogRepository,
        ConfigurationRepository $configurationRepository,
        LoggerInterface $logger
    ) {
        $this->apiClientFactory = $apiClientFactory;
        $this->priceNormaliser = $priceNormaliser;
        $this->availabilityWriter = $availabilityWriter;
        $this->schemaMigrator = $schemaMigrator;
        $this->roomTypeWriter = $roomTypeWriter;
        $this->rateWriter = $rateWriter;
        $this->cacheTagFlusher = $cacheTagFlusher;
        $this->syncLogRepository = $syncLogRepository;
        $this->configurationRepository = $configurationRepository;
        $this->logger = $logger;
    }

    /**
     * @return array<string, SiteConfigurationDto>
     */
    public function getConfiguredSites(?string $onlySiteIdentifier = null): array
    {
        $sites = $this->apiClientFactory->getAllConfiguredSites();
        if ($onlySiteIdentifier === null || $onlySiteIdentifier === '') {
            return $sites;
        }

        if (!isset($sites[$onlySiteIdentifier])) {
            return [];
        }

        return [$onlySiteIdentifier => $sites[$onlySiteIdentifier]];
    }

    public function syncSite(
        SiteConfigurationDto $config,
        bool $force = false,
        ?int $overrideDays = null
    ): SyncSiteResult {
        $logUid = $this->syncLogRepository->open($config->siteIdentifier);

        try {
            $this->schemaMigrator->migrateIfNeeded();

            $rangeDays = $overrideDays !== null ? $overrideDays : $config->syncRangeDays;
            $from = new DateTimeImmutable('today');
            $until = $from->modify('+' . (max(1, $rangeDays) - 1) . ' days');

            $roomTypesClient = $this->apiClientFactory->createRoomTypesClient($config);
            $roomTypes = $roomTypesClient->fetchAll();
            $roomTypesWritten = $this->roomTypeWriter->write($config, $roomTypes);

            $ratesClient = $this->apiClientFactory->createRatesClient($config);
            $rates = $ratesClient->fetchAll();
            $ratesWritten = $this->rateWriter->write($config, $rates);

            if ($roomTypes === []) {
                $message = 'No room types returned from API.';
                $this->syncLogRepository->complete(
                    $logUid,
                    SyncLog::STATUS_PARTIAL,
                    0,
                    0,
                    0,
                    $message
                );

                return new SyncSiteResult(
                    true,
                    0,
                    0,
                    0,
                    $roomTypesWritten,
                    $ratesWritten,
                    $message
                );
            }

            $calendarClient = $this->apiClientFactory->createCalendarDatesClient($config);
            $monthCursor = new DateTimeImmutable($from->format('Y-m-01'));
            $monthEnd = new DateTimeImmutable($until->format('Y-m-01'));
            $occupancy = [$config->defaultOccupancy];
            $emptyStayFilter = [
                'companyIdentifiers' => [],
                'roomTypeIds' => [],
                'rateIds' => [],
            ];

            $totalWritten = 0;
            $totalChanged = 0;
            $allChangedRoomIds = [];

            while ($monthCursor <= $monthEnd) {
                foreach ($roomTypes as $roomType) {
                    $roomFilter = $emptyStayFilter;
                    $roomFilter['roomTypeIds'] = [$roomType->id];

                    $calendarDates = $calendarClient->fetchMonth(
                        $monthCursor,
                        $occupancy,
                        $roomFilter
                    );

                    $rows = $this->priceNormaliser->normaliseRoomPrices(
                        $config,
                        $calendarDates,
                        $roomType->id
                    );

                    if ($rows === []) {
                        continue;
                    }

                    if ($force) {
                        $allChangedRoomIds[$roomType->id] = true;
                    }
                    $result = $this->availabilityWriter->write($config, $rows);
                    $totalWritten += $result->rowsWritten;
                    $totalChanged += $result->rowsChanged;
                    foreach ($result->changedRoomIds as $rid) {
                        $allChangedRoomIds[$rid] = true;
                    }
                }

                foreach ($rates as $rate) {
                    if (!$rate->isPackage) {
                        continue;
                    }

                    $packageFilter = $emptyStayFilter;
                    $packageFilter['rateIds'] = [$rate->id];

                    $calendarDates = $calendarClient->fetchMonth(
                        $monthCursor,
                        $occupancy,
                        $packageFilter
                    );

                    $rows = $this->priceNormaliser->normalisePackagePrices(
                        $config,
                        $calendarDates,
                        $rate->id
                    );

                    if ($rows === []) {
                        continue;
                    }

                    $result = $this->availabilityWriter->write($config, $rows);
                    $totalWritten += $result->rowsWritten;
                    $totalChanged += $result->rowsChanged;
                }

                $monthCursor = $monthCursor->modify('+1 month');
            }

            $this->availabilityWriter->purgePastDates($config);

            $tagsFlushed = $this->cacheTagFlusher->flush(
                $config,
                array_keys($allChangedRoomIds)
            );

            $message = sprintf(
                'Window %s–%s',
                $from->format('Y-m-d'),
                $until->format('Y-m-d')
            );
            $this->syncLogRepository->complete(
                $logUid,
                SyncLog::STATUS_SUCCESS,
                $totalWritten,
                $totalChanged,
                $tagsFlushed,
                $message
            );

            return new SyncSiteResult(
                true,
                $totalWritten,
                $totalChanged,
                $tagsFlushed,
                $roomTypesWritten,
                $ratesWritten,
                $message
            );
        } catch (Throwable $e) {
            $this->logger->error(
                'CASABLANCA sync failed for site {site}: {error}',
                [
                    'site' => $config->siteIdentifier,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]
            );
            $this->syncLogRepository->complete(
                $logUid,
                SyncLog::STATUS_FAILURE,
                0,
                0,
                0,
                $e->getMessage()
            );

            return new SyncSiteResult(
                false,
                0,
                0,
                0,
                0,
                0,
                $e->getMessage()
            );
        }
    }

    /**
     * @return array{success: bool, message: string, roomTypeCount?: int}
     */
    public function testConnection(string $siteIdentifier): array
    {
        if ($siteIdentifier === '') {
            return [
                'success' => false,
                'message' => 'Site identifier is required.',
            ];
        }

        try {
            $config = $this->apiClientFactory->resolveBySiteIdentifier($siteIdentifier);
            if ($config === null) {
                $message = 'No configuration found for this site.';
                $this->configurationRepository->updateConnectionStatus(
                    $siteIdentifier,
                    'error',
                    $message
                );

                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            $roomTypesClient = $this->apiClientFactory->createRoomTypesClient($config);
            $roomTypes = $roomTypesClient->fetchAll();
            $count = count($roomTypes);

            if ($count === 0) {
                $message = 'Connection OK, but the API returned no room types for this space.';
                $this->configurationRepository->updateConnectionStatus(
                    $siteIdentifier,
                    'ok',
                    $message
                );

                return [
                    'success' => true,
                    'message' => $message,
                    'roomTypeCount' => 0,
                ];
            }

            $message = sprintf(
                'Connection OK — %d room type(s) found for space "%s".',
                $count,
                $config->urlFriendlyIbeContextId
            );
            $this->configurationRepository->updateConnectionStatus(
                $siteIdentifier,
                'ok',
                $message
            );

            return [
                'success' => true,
                'message' => $message,
                'roomTypeCount' => $count,
            ];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $this->configurationRepository->updateConnectionStatus(
                $siteIdentifier,
                'error',
                $message
            );

            return [
                'success' => false,
                'message' => $message,
            ];
        }
    }

    public function sync(?string $siteIdentifier, bool $force, ?int $overrideDays): int
    {
        $sites = $this->getConfiguredSites($siteIdentifier);
        if ($sites === []) {
            $this->logger->warning(
                'CASABLANCA sync: no configured sites found{filter}.',
                [
                    'filter' => $siteIdentifier !== null && $siteIdentifier !== ''
                        ? ' for site "' . $siteIdentifier . '"'
                        : '',
                ]
            );

            return 1;
        }

        $hadFailure = false;
        foreach ($sites as $config) {
            $result = $this->syncSite($config, $force, $overrideDays);
            if (!$result->success) {
                $hadFailure = true;
            }
        }

        return $hadFailure ? 1 : 0;
    }
}
