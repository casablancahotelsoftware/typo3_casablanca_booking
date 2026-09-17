<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(static function (): void {
    $extensionName = 'CasablancaBooking';
    $iconIdentifier = 'casablanca-booking-widget';
    $groupName = 'plugins';

    $plugins = [
        'Widget' => [
            'title' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.widget.title',
            'description' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.widget.description',
            'flexform' => 'Configuration/FlexForms/Widget.xml',
        ],
        'SearchBar' => [
            'title' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.searchbar.title',
            'description' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.searchbar.description',
            'flexform' => 'Configuration/FlexForms/SearchBar.xml',
        ],
        'RoomTypes' => [
            'title' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.roomtypes.title',
            'description' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.roomtypes.description',
            'flexform' => 'Configuration/FlexForms/RoomTypes.xml',
        ],
        'Packages' => [
            'title' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.packages.title',
            'description' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.packages.description',
            'flexform' => 'Configuration/FlexForms/Packages.xml',
        ],
        'PriceTeaser' => [
            'title' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.priceteaser.title',
            'description' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:plugin.priceteaser.description',
            'flexform' => 'Configuration/FlexForms/PriceTeaser.xml',
        ],
    ];

    foreach ($plugins as $pluginName => $pluginConfig) {
        // v13.4 migration: CType value is "<extname>_<pluginname>" in lowercase.
        $pluginSignature = strtolower($extensionName) . '_' . strtolower($pluginName);

        ExtensionUtility::registerPlugin(
            $extensionName,
            $pluginName,
            $pluginConfig['title'],
            $iconIdentifier,
            $groupName,
            $pluginConfig['description'],
        );

        ExtensionManagementUtility::addPiFlexFormValue(
            '*',
            'FILE:EXT:casablanca_booking/' . $pluginConfig['flexform'],
            $pluginSignature,
        );

        ExtensionManagementUtility::addToAllTCAtypes(
            'tt_content',
            '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:plugin, pi_flexform',
            $pluginSignature,
            'after:palette:headers'
        );
    }
});
