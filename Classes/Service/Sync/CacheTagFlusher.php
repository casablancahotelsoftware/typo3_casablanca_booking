<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * Targeted cache invalidation for the page cache.
 */
final class CacheTagFlusher
{
    private const SITE_WIDE_THRESHOLD = 10;

    /** @var CacheManager */
    private $cacheManager;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param string[] $changedRoomIds
     */
    public function flush(SiteConfigurationDto $config, array $changedRoomIds): int
    {
        if ($changedRoomIds === []) {
            return 0;
        }

        $pageCache = $this->cacheManager->getCache('pages');

        if (count($changedRoomIds) >= self::SITE_WIDE_THRESHOLD) {
            $pageCache->flushByTag($config->getCacheTag());

            return 1;
        }

        $count = 0;
        foreach ($changedRoomIds as $roomId) {
            $pageCache->flushByTag($config->getCacheTagForRoom($roomId));
            $count++;
        }

        return $count;
    }
}
