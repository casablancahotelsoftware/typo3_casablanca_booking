<?php

defined('TYPO3') || defined('TYPO3_MODE') or die();

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Controller\BookingWidgetController;
use Casablanca\CasablancaBooking\Controller\PackagesController;
use Casablanca\CasablancaBooking\Controller\PriceTeaserController;
use Casablanca\CasablancaBooking\Controller\RoomTypesController;
use Casablanca\CasablancaBooking\Controller\SearchBarController;
use Casablanca\CasablancaBooking\Hook\CacheFlushSyncHook;
use Casablanca\CasablancaBooking\Task\SyncAvailabilityTask;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

call_user_func(static function (): void {
    Typo3Adapter::configurePlugin(
        'CasablancaBooking',
        'Widget',
        [BookingWidgetController::class => 'show,redirect,fetchCalendar,fetchOffers'],
        [BookingWidgetController::class => 'redirect,fetchCalendar,fetchOffers']
    );

    Typo3Adapter::configurePlugin(
        'CasablancaBooking',
        'SearchBar',
        [SearchBarController::class => 'show,redirect'],
        [SearchBarController::class => 'redirect']
    );

    Typo3Adapter::configurePlugin(
        'CasablancaBooking',
        'RoomTypes',
        [RoomTypesController::class => 'show,fetchCalendar,fetchOffers'],
        [RoomTypesController::class => 'fetchCalendar,fetchOffers']
    );

    Typo3Adapter::configurePlugin(
        'CasablancaBooking',
        'Packages',
        [PackagesController::class => 'show,fetchCalendar,fetchOffers'],
        [PackagesController::class => 'fetchCalendar,fetchOffers']
    );

    Typo3Adapter::configurePlugin(
        'CasablancaBooking',
        'PriceTeaser',
        [PriceTeaserController::class => 'show']
    );

    /** @var IconRegistry $iconRegistry */
    $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
    $iconRegistry->registerIcon(
        'casablanca-booking-widget',
        SvgIconProvider::class,
        ['source' => 'EXT:casablanca_booking/Resources/Public/Icons/plugin-widget.svg']
    );

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['casablanca'][] =
        'Casablanca\\CasablancaBooking\\ViewHelper';

    if (!Typo3Adapter::isAtLeast(14)) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][SyncAvailabilityTask::class] = [
            'extension' => 'casablanca_booking',
            'title' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_mod.xlf:scheduler.task.title',
            'description' => 'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_mod.xlf:scheduler.task.description',
        ];
    }

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] =
        static function (array $params, $dataHandler): void {
            GeneralUtility::makeInstance(CacheFlushSyncHook::class)
                ->syncAfterCacheFlush($params, $dataHandler);
        };

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['CasablancaRoomSlug'] =
        \Casablanca\CasablancaBooking\Routing\RoomSlugAspect::class;

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['CasablancaPackageSlug'] =
        \Casablanca\CasablancaBooking\Routing\PackageSlugAspect::class;

    Typo3Adapter::registerRoomTypesCacheHashParameters();
    Typo3Adapter::registerPackagesCacheHashParameters();
    Typo3Adapter::registerWidgetCalendarCacheHashExclusions();
    Typo3Adapter::registerWidgetCalendarPageType();

    $typoScriptSetup = '
plugin.tx_casablancabooking {
    view {
        templateRootPaths {
            0 = EXT:casablanca_booking/Resources/Private/Templates/
            10 = {$plugin.tx_casablancabooking.view.templateRootPath}
        }
        partialRootPaths {
            0 = EXT:casablanca_booking/Resources/Private/Partials/
            10 = {$plugin.tx_casablancabooking.view.partialRootPath}
        }
        layoutRootPaths {
            0 = EXT:casablanca_booking/Resources/Private/Layouts/
            10 = {$plugin.tx_casablancabooking.view.layoutRootPath}
        }
    }
    settings {
        windowDays = 90
        defaultRooms = 1
        defaultAdults = 2
        defaultChildren = 0
        defaultChildrenAges =
        language =
        stayNights = 7
        includeCss = 1
        appearance = default
        theme {
            accent =
            accentContrast =
            radius =
        }
    }
}
';

    if (!Typo3Adapter::isAtLeast(12)) {
        $typoScriptSetup .= '
page {
    includeCSS {
        casablancaBookingWidget = EXT:casablanca_booking/Resources/Public/Css/widget.css
    }
    includeJSFooter {
        casablancaBookingWidget = EXT:casablanca_booking/Resources/Public/JavaScript/widget.js
        casablancaBookingWidget.defer = 1
    }
}
';
    }

    ExtensionManagementUtility::addTypoScriptSetup($typoScriptSetup);
});
