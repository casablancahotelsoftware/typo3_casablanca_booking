<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Hook;

use Casablanca\CasablancaBooking\Service\Sync\SyncTriggerService;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Triggers availability sync after backend frontend or full cache clears.
 */
final class CacheFlushSyncHook
{
    /** @var SyncTriggerService */
    private $syncTriggerService;

    public function __construct(SyncTriggerService $syncTriggerService)
    {
        $this->syncTriggerService = $syncTriggerService;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function syncAfterCacheFlush(array $params, DataHandler $dataHandler): void
    {
        unset($dataHandler);

        $cacheCmd = strtolower((string)($params['cacheCmd'] ?? ''));
        if (!in_array($cacheCmd, ['pages', 'all'], true)) {
            return;
        }

        $this->syncTriggerService->triggerSync('cache_flush');
    }
}
