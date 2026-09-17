<?php

defined('TYPO3') || defined('TYPO3_MODE') or die();

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Controller\Backend\ConfigurationController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(static function (): void {
    if (Typo3Adapter::isAtLeast(12)) {
        return;
    }

    ExtensionUtility::registerModule(
        'CasablancaBooking',
        'tools',
        'casablancabooking',
        '',
        [
            ConfigurationController::class => 'index,edit,save,testConnection,syncNow',
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:casablanca_booking/Resources/Public/Icons/plugin-widget.svg',
            'labels' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_mod.xlf',
            'navigationComponentId' => '',
            'inheritNavigationComponentFromMainModule' => false,
        ]
    );
});
