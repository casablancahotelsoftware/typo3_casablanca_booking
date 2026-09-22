<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Model\SyncLog;
use Casablanca\CasablancaBooking\Domain\Repository\AvailabilityRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use Casablanca\CasablancaBooking\Domain\Repository\SyncLogRepository;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * Removes mirrored catalog and availability rows for one site before a fresh sync.
 */
final class SyncCatalogFlusher
{
    public function __construct(
        private readonly AvailabilityRepository $availabilityRepository,
        private readonly RoomTypeRepository $roomTypeRepository,
        private readonly RateRepository $rateRepository,
        private readonly CacheManager $cacheManager,
        private readonly SyncLogRepository $syncLogRepository,
    ) {}

    /**
     * @return array{
     *     roomTypesDeleted: int,
     *     ratesDeleted: int,
     *     availabilityDeleted: int,
     *     cacheTagsFlushed: int,
     *     message: string
     * }
     */
    public function flushSite(string $siteIdentifier): array
    {
        $logUid = $this->syncLogRepository->open($siteIdentifier);

        $roomTypesDeleted = $this->roomTypeRepository->deleteBySiteIdentifier($siteIdentifier);
        $ratesDeleted = $this->rateRepository->deleteBySiteIdentifier($siteIdentifier);
        $availabilityDeleted = $this->availabilityRepository->deleteBySiteIdentifier($siteIdentifier);

        $pageCache = $this->cacheManager->getCache('pages');
        $pageCache->flushByTag('casablanca_ari_' . $siteIdentifier);
        $cacheTagsFlushed = 1;

        $deletedTotal = $roomTypesDeleted + $ratesDeleted + $availabilityDeleted;
        $message = sprintf(
            'Cleared synced catalog: %d room type(s), %d rate(s)/package(s), %d availability row(s).',
            $roomTypesDeleted,
            $ratesDeleted,
            $availabilityDeleted
        );

        $this->syncLogRepository->complete(
            $logUid,
            SyncLog::STATUS_SUCCESS,
            0,
            $deletedTotal,
            $cacheTagsFlushed,
            $message
        );

        return [
            'roomTypesDeleted' => $roomTypesDeleted,
            'ratesDeleted' => $ratesDeleted,
            'availabilityDeleted' => $availabilityDeleted,
            'cacheTagsFlushed' => $cacheTagsFlushed,
            'message' => $message,
        ];
    }
}
