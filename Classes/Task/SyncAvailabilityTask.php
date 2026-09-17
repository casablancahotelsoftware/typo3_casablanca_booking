<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Task;

use Casablanca\CasablancaBooking\Service\Sync\SyncService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task wrapper around SyncService.
 */
class SyncAvailabilityTask extends AbstractTask
{
    public function execute(): bool
    {
        /** @var SyncService $syncService */
        $syncService = GeneralUtility::makeInstance(SyncService::class);

        return $syncService->sync(null, false, null) === 0;
    }
}
