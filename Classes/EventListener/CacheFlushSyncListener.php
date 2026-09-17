<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\EventListener;

use Casablanca\CasablancaBooking\Service\Sync\SyncTriggerService;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;

/**
 * Triggers availability sync after CLI cache:flush for pages or full cache groups.
 */
final class CacheFlushSyncListener
{
    /** @var SyncTriggerService */
    private $syncTriggerService;

    public function __construct(SyncTriggerService $syncTriggerService)
    {
        $this->syncTriggerService = $syncTriggerService;
    }

    public function __invoke(CacheFlushEvent $event): void
    {
        if (!$event->hasGroup('pages')) {
            return;
        }

        $this->syncTriggerService->triggerSync('cache_flush_cli');
    }
}
