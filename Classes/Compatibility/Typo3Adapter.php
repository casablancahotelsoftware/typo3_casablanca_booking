<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Compatibility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

final class Typo3Adapter
{
    public const CALENDAR_PAGE_TYPE = 1729162800;
    public const CALENDAR_PAGE_TYPE_ROOM_TYPES = 1729162801;
    public const CALENDAR_PAGE_TYPE_PACKAGES = 1729162802;
    public static function getMajorVersion(): int
    {
        if (class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)) {
            return GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class)
                ->getMajorVersion();
        }

        if (method_exists(VersionNumberUtility::class, 'getCurrentMajorVersion')) {
            return (int)VersionNumberUtility::getCurrentMajorVersion();
        }

        if (defined('TYPO3_version')) {
            $parts = explode('.', (string)constant('TYPO3_version'));

            return (int)($parts[0] ?? 10);
        }

        return 10;
    }

    public static function isAtLeast(int $major): bool
    {
        return self::getMajorVersion() >= $major;
    }

    public static function usesContentElementPlugins(): bool
    {
        return self::isAtLeast(12);
    }

    /**
     * @param array<int, string> $cacheableActions
     * @param array<int, string> $nonCacheableActions
     */
    public static function configurePlugin(
        string $extensionName,
        string $pluginName,
        array $cacheableActions,
        array $nonCacheableActions = []
    ): void {
        if (self::usesContentElementPlugins()) {
            ExtensionUtility::configurePlugin(
                $extensionName,
                $pluginName,
                $cacheableActions,
                $nonCacheableActions,
                ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
            );
            return;
        }

        ExtensionUtility::configurePlugin(
            $extensionName,
            $pluginName,
            $cacheableActions,
            $nonCacheableActions
        );
    }

    public static function getPluginSignature(string $extensionName, string $pluginName): string
    {
        return strtolower($extensionName) . '_' . strtolower($pluginName);
    }

    /**
     * @param array<int, string> $tagNames
     */
    public static function addPageCacheTags(ServerRequestInterface $request, array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        if (self::isAtLeast(13)) {
            $collector = $request->getAttribute('frontend.cache.collector');
            if ($collector === null || !class_exists(\TYPO3\CMS\Core\Cache\CacheTag::class)) {
                return;
            }
            $cacheTags = [];
            foreach ($tagNames as $name) {
                $cacheTags[] = new \TYPO3\CMS\Core\Cache\CacheTag($name);
            }
            $collector->addCacheTags(...$cacheTags);
            return;
        }

        $tsfe = self::getTypoScriptFrontendController();
        if ($tsfe !== null && method_exists($tsfe, 'addCacheTags')) {
            $tsfe->addCacheTags($tagNames);
        }
    }

    public static function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'] ?? null;
    }

    /**
     * @param mixed $result
     * @return array<int, array<string, mixed>>
     */
    public static function fetchAllAssociative($result): array
    {
        if (is_object($result) && method_exists($result, 'fetchAllAssociative')) {
            return $result->fetchAllAssociative();
        }
        if (is_object($result) && method_exists($result, 'fetchAll')) {
            /** @var array<int, array<string, mixed>> $rows */
            $rows = $result->fetchAll();
            return $rows;
        }

        return [];
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public static function fetchOne($result)
    {
        if (is_object($result) && method_exists($result, 'fetchOne')) {
            return $result->fetchOne();
        }
        if (is_object($result) && method_exists($result, 'fetchColumn')) {
            return $result->fetchColumn();
        }

        return false;
    }

    /**
     * @param mixed $queryBuilder
     * @return mixed
     */
    public static function executeQuery($queryBuilder)
    {
        if (method_exists($queryBuilder, 'executeQuery')) {
            return $queryBuilder->executeQuery();
        }

        return $queryBuilder->execute();
    }

    /**
     * @return int|string
     */
    public static function stringArrayParameterType()
    {
        if (class_exists(\Doctrine\DBAL\ArrayParameterType::class)) {
            return \Doctrine\DBAL\ArrayParameterType::STRING;
        }

        return \TYPO3\CMS\Core\Database\Connection::PARAM_STR_ARRAY;
    }

    public static function isArrayList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    public static function redirect303(string $url): void
    {
        if (class_exists(\TYPO3\CMS\Core\Http\PropagateResponseException::class)) {
            throw new \TYPO3\CMS\Core\Http\PropagateResponseException(
                new \TYPO3\CMS\Core\Http\RedirectResponse($url, 303),
                200
            );
        }

        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
        }
        exit;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function includeFrontendAssets(array $settings = []): void
    {
        $includeCss = (int)($settings['includeCss'] ?? 1) === 1;

        if (self::isAtLeast(12)) {
            $assetCollector = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Page\AssetCollector::class
            );
            if ($includeCss) {
                $assetCollector->addStyleSheet(
                    'casablancaBookingWidget',
                    'EXT:casablanca_booking/Resources/Public/Css/widget.css'
                );
            }
            $assetCollector->addJavaScript(
                'casablancaBookingWidget',
                'EXT:casablanca_booking/Resources/Public/JavaScript/widget.js',
                ['defer' => true]
            );
            return;
        }

        $tsfe = self::getTypoScriptFrontendController();
        if ($tsfe === null) {
            return;
        }

        if ($includeCss) {
            $tsfe->getPageRenderer()->addCssFile(
                'EXT:casablanca_booking/Resources/Public/Css/widget.css'
            );
        }
        $tsfe->getPageRenderer()->addJsFooterFile(
            'EXT:casablanca_booking/Resources/Public/JavaScript/widget.js',
            'text/javascript',
            false,
            false,
            '',
            true
        );
    }

    public static function registerRoomTypesCacheHashParameters(): void
    {
        self::registerPluginCacheHashParameter('CasablancaBooking', 'RoomTypes', 'roomSlug');
    }

    public static function registerPackagesCacheHashParameters(): void
    {
        self::registerPluginCacheHashParameter('CasablancaBooking', 'Packages', 'packageSlug');
    }

    /**
     * Exclude live calendar fetch GET params from cHash so AJAX can append form values.
     */
    public static function registerWidgetCalendarCacheHashExclusions(): void
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'])) {
            $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = [];
        }

        $excluded = [
            'arrival',
            'departure',
            'windowDays',
            'numberOfRooms',
            'room',
            'rateIds',
            'offerMode',
            '^rooms[',
        ];

        foreach ($excluded as $parameterName) {
            if (!in_array($parameterName, $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'], true)) {
                $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = $parameterName;
            }
        }
    }

    /**
     * Dedicated page types so fetchCalendar/fetchOffers return JSON only (not full page HTML).
     */
    public static function registerWidgetCalendarPageType(): void
    {
        self::registerCalendarPageType(self::CALENDAR_PAGE_TYPE, 'Widget');
        self::registerCalendarPageType(self::CALENDAR_PAGE_TYPE_ROOM_TYPES, 'RoomTypes');
        self::registerCalendarPageType(self::CALENDAR_PAGE_TYPE_PACKAGES, 'Packages');
    }

    private static function registerCalendarPageType(int $pageType, string $pluginName): void
    {
        ExtensionManagementUtility::addTypoScriptSetup(
            'type_' . $pageType . ' = PAGE
type_' . $pageType . ' {
  typeNum = ' . $pageType . '
  config {
    disableAllHeaderCode = 1
    admPanel = 0
  }
  10 = USER_INT
  10 {
    userFunc = TYPO3\\CMS\\Extbase\\Core\\Bootstrap->run
    extensionName = CasablancaBooking
    pluginName = ' . $pluginName . '
  }
}'
        );
    }

    private static function registerPluginCacheHashParameter(
        string $extensionName,
        string $pluginName,
        string $argumentName
    ): void {
        $pluginSignature = self::getPluginSignature($extensionName, $pluginName);
        $parameterName = $pluginSignature . '[' . $argumentName . ']';

        if (!isset($GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['cachedParametersWhiteList'])) {
            $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['cachedParametersWhiteList'] = [];
        }

        if (!in_array($parameterName, $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['cachedParametersWhiteList'], true)) {
            $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['cachedParametersWhiteList'][] = $parameterName;
        }
    }
}
