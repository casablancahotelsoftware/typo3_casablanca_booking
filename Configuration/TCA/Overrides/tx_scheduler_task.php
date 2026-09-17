<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Casablanca\CasablancaBooking\Task\SyncAvailabilityTask;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (isset($GLOBALS['TCA']['tx_scheduler_task'])) {
    ExtensionManagementUtility::addRecordType(
        [
            'label' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_mod.xlf:scheduler.task.title',
            'description' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_mod.xlf:scheduler.task.description',
            'value' => SyncAvailabilityTask::class,
            'icon' => 'casablanca-booking-widget',
            'iconOverlay' => 'content-clock',
            'group' => 'casablanca_booking',
        ],
        $GLOBALS['TCA']['tx_scheduler_task']['types']['0']['showitem'],
        [],
        '',
        'tx_scheduler_task'
    );
}
