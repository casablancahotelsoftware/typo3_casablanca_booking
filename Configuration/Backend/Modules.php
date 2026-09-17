<?php

declare(strict_types=1);

use Casablanca\CasablancaBooking\Controller\Backend\ConfigurationController;

/**
 * TYPO3 v12+ backend module registration.
 */
return [
    'casablanca_booking' => [
        'parent' => 'tools',
        'path' => '/module/casablanca/booking',
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'casablanca-booking-widget',
        'labels' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'CasablancaBooking',
        'controllerActions' => [
            ConfigurationController::class => [
                'index',
                'edit',
                'save',
                'testConnection',
                'syncNow',
            ],
        ],
    ],
];
