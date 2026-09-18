<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Model\Availability;
use Casablanca\CasablancaBooking\Service\Api\ApiClientFactory;
use Casablanca\CasablancaBooking\Service\Frontend\LabelResolver;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use DateTimeImmutable;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Shared frontend logic for all CASABLANCA content elements.
 */
abstract class AbstractWidgetController extends ActionController
{
    /** @var SiteFinder */
    protected $siteFinder;

    /** @var ApiClientFactory */
    protected $apiClientFactory;

    /** @var IbeUrlBuilder */
    protected $ibeUrlBuilder;

    public function __construct(
        SiteFinder $siteFinder,
        ApiClientFactory $apiClientFactory,
        IbeUrlBuilder $ibeUrlBuilder
    ) {
        $this->siteFinder = $siteFinder;
        $this->apiClientFactory = $apiClientFactory;
        $this->ibeUrlBuilder = $ibeUrlBuilder;
    }

    protected function resolveConfig(): ?SiteConfigurationDto
    {
        $site = $this->siteFinder->getSiteByPageId($this->resolvePageId());

        return $this->apiClientFactory->resolveConfiguration($site);
    }

    protected function resolvePageId(): int
    {
        $pageInfo = $this->request->getAttribute('frontend.page.information');
        if ($pageInfo !== null && method_exists($pageInfo, 'getId')) {
            return (int)$pageInfo->getId();
        }

        return (int)($GLOBALS['TSFE']->id ?? 0);
    }

    private const MAX_ROOMS = 5;

    /**
     * @return array{
     *     adults: int,
     *     defaultChildrenCount: int,
     *     childrenAges: int[],
     *     culture: string,
     *     windowDays: int,
     *     defaultRooms: int
     * }
     */
    protected function parseCommonSettings(SiteConfigurationDto $config): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        $adults = max(1, (int)($settings['defaultAdults'] ?? $config->defaultOccupancy->numberOfAdults));
        $defaultChildrenCount = max(0, (int)(
            $settings['defaultChildren']
                ?? count($config->defaultOccupancy->ageOfChildren)
        ));
        $childrenAges = $this->parseChildrenAges(
            (string)($settings['defaultChildrenAges'] ?? ''),
            $config
        );

        if (count($childrenAges) > $defaultChildrenCount) {
            $childrenAges = array_slice($childrenAges, 0, $defaultChildrenCount);
        }

        $culture = trim((string)($settings['language'] ?? $config->defaultCulture));
        $windowDays = max(7, min(180, (int)($settings['windowDays'] ?? 90)));
        $defaultRooms = max(1, min(self::MAX_ROOMS, (int)($settings['defaultRooms'] ?? 1)));

        return [
            'adults' => $adults,
            'defaultChildrenCount' => $defaultChildrenCount,
            'childrenAges' => $childrenAges,
            'culture' => $culture,
            'windowDays' => $windowDays,
            'defaultRooms' => $defaultRooms,
        ];
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    protected function parseIbeLinkTarget(?array $settings = null): string
    {
        $settings ??= is_array($this->settings) ? $this->settings : [];
        $target = trim((string)($settings['ibeLinkTarget'] ?? '_self'));

        return in_array($target, ['_self', '_blank'], true) ? $target : '_self';
    }

    /**
     * @return array{adults: int, children: int}
     */
    protected function clampOccupancyToMax(int $adults, int $children, int $maxOccupancy): array
    {
        if ($maxOccupancy <= 0) {
            return [
                'adults' => max(1, $adults),
                'children' => max(0, $children),
            ];
        }

        $adults = max(1, $adults);
        $children = max(0, $children);

        while ($adults + $children > $maxOccupancy) {
            if ($children > 0) {
                $children--;
            } elseif ($adults > 1) {
                $adults--;
            } else {
                break;
            }
        }

        return [
            'adults' => $adults,
            'children' => $children,
        ];
    }

    /**
     * @return array<int, array{adults: int, children: int, childrenAges: int[], childrenAgesString: string}>
     */
    protected function buildDefaultRooms(array $common): array
    {
        $rooms = [];
        $roomCount = max(1, min(self::MAX_ROOMS, (int)($common['defaultRooms'] ?? 1)));
        $ages = $this->padAgesToCount($common['childrenAges'], $common['defaultChildrenCount']);

        for ($i = 0; $i < $roomCount; $i++) {
            $rooms[] = [
                'adults' => $common['adults'],
                'children' => $common['defaultChildrenCount'],
                'childrenAges' => $ages,
                'childrenAgesString' => implode(', ', $ages),
            ];
        }

        return $rooms;
    }

    /**
     * @return RoomOccupancyDto[]
     */
    protected function parseRoomOccupanciesFromRequest(): array
    {
        $params = array_merge(
            $this->request->getQueryParams(),
            (array)$this->request->getParsedBody()
        );

        $rooms = isset($params['rooms']) && is_array($params['rooms']) ? $params['rooms'] : [];
        $occupancies = [];

        if ($rooms !== []) {
            $roomCount = min(self::MAX_ROOMS, count($rooms));
            for ($i = 0; $i < $roomCount; $i++) {
                if (!isset($rooms[$i]) || !is_array($rooms[$i])) {
                    continue;
                }
                $room = $rooms[$i];
                $adults = max(1, (int)($room['adults'] ?? 2));
                $children = max(0, (int)($room['children'] ?? 0));
                $ages = $this->parseAgesFromRoomInput($room, $children);
                $occupancies[] = $this->buildOccupancy($adults, $children, $ages);
            }
        }

        if ($occupancies !== []) {
            return $occupancies;
        }

        $adults = max(1, (int)($params['adults'] ?? 2));
        $children = max(0, (int)($params['children'] ?? 0));
        $ages = [];
        if (isset($params['childrenAges']) && is_string($params['childrenAges']) && $params['childrenAges'] !== '') {
            $ages = array_values(array_map('intval', explode(',', $params['childrenAges'])));
        }

        return [$this->buildOccupancy($adults, $children, $ages)];
    }

    /**
     * @param array<string, mixed> $room
     * @return int[]
     */
    private function parseAgesFromRoomInput(array $room, int $children): array
    {
        if (isset($room['childAge']) && is_array($room['childAge'])) {
            $ages = array_values(array_map(static function ($age): int {
                return max(0, min(17, (int)$age));
            }, $room['childAge']));

            return $this->padAgesToCount($ages, $children);
        }

        if (isset($room['childrenAges']) && is_string($room['childrenAges']) && $room['childrenAges'] !== '') {
            $ages = array_values(array_map('intval', explode(',', $room['childrenAges'])));

            return $this->padAgesToCount($ages, $children);
        }

        return $this->padAgesToCount([], $children);
    }

    protected function tagPageCache(SiteConfigurationDto $config, string $preselectedRoomTypeId = ''): void
    {
        $tagNames = [$config->getCacheTag()];
        if ($preselectedRoomTypeId !== '') {
            $tagNames[] = $config->getCacheTagForRoom($preselectedRoomTypeId);
        }

        Typo3Adapter::addPageCacheTags($this->request, $tagNames);
    }

    /**
     * @param Availability[] $availability
     * @return array<string, mixed>
     */
    protected function buildJsonLd(
        SiteConfigurationDto $config,
        array $availability,
        ?string $roomName = null
    ): array {
        $lowestPrice = null;
        $lowestCurrency = 'EUR';

        foreach ($availability as $day) {
            if (!$day->isAvailable()) {
                continue;
            }
            $price = $day->getFromPrice();
            if ($price === null) {
                continue;
            }
            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestCurrency = $day->getCurrency();
            }
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'Hotel',
            'identifier' => $config->siteIdentifier,
        ];

        if ($lowestPrice !== null) {
            $payload['makesOffer'] = [
                '@type' => 'Offer',
                'name' => $roomName !== null
                    ? sprintf('Starting rate for %s', $roomName)
                    : 'Starting rate',
                'priceCurrency' => $lowestCurrency,
                'price' => number_format($lowestPrice, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
            ];
        }

        return $payload;
    }

    /** @return int[] */
    protected function parseChildrenAges(string $raw, SiteConfigurationDto $config): array
    {
        if ($raw === '') {
            return $config->defaultOccupancy->ageOfChildren;
        }

        $parts = array_filter(
            array_map('trim', explode(',', $raw)),
            static function (string $s): bool {
                return $s !== '' && ctype_digit($s);
            }
        );

        return array_map('intval', $parts);
    }

    /**
     * @param int[] $ages
     * @return int[]
     */
    protected function padAgesToCount(array $ages, int $count): array
    {
        $ages = array_values($ages);
        while (count($ages) < $count) {
            $ages[] = 0;
        }
        if (count($ages) > $count) {
            $ages = array_slice($ages, 0, $count);
        }

        return $ages;
    }

    protected function includeFrontendAssets(): void
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $this->assignThemePresentationVariables();
        $this->assignLabelPresentationVariables();
        Typo3Adapter::includeFrontendAssets($settings);
    }

    protected function assignThemePresentationVariables(): void
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $appearance = $this->normalizeAppearance((string)($settings['appearance'] ?? 'default'));

        $this->view->assignMultiple([
            'cbAppearance' => $appearance,
            'cbWidgetClasses' => 'cb-widget cb-widget--' . $appearance,
            'cbThemeStyle' => $this->buildThemeInlineStyle($settings),
        ]);
    }

    protected function assignLabelPresentationVariables(): void
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $labels = is_array($settings['labels'] ?? null) ? $settings['labels'] : [];

        /** @var LabelResolver $labelResolver */
        $labelResolver = GeneralUtility::makeInstance(LabelResolver::class);
        $cbLabels = $labelResolver->buildJavaScriptLabels($labels);

        $this->view->assignMultiple([
            'labels' => $labels,
            'cbLabelsJson' => json_encode($cbLabels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    protected function normalizeAppearance(string $appearance): string
    {
        return in_array($appearance, ['default', 'inherit', 'compact'], true) ? $appearance : 'default';
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function buildThemeInlineStyle(array $settings): string
    {
        $theme = is_array($settings['theme'] ?? null) ? $settings['theme'] : [];
        $map = [
            'accent' => '--cb-accent',
            'accentContrast' => '--cb-accent-contrast',
            'radius' => '--cb-radius',
        ];
        $parts = [];
        foreach ($map as $key => $cssVar) {
            $value = trim((string)($theme[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $cssVar . ':' . $value;
            }
        }

        return $parts === [] ? '' : implode(';', $parts);
    }

    /**
     * @param int[] $ages
     */
    protected function buildOccupancy(int $adults, int $children, array $ages): RoomOccupancyDto
    {
        $childCount = max(0, $children);
        $ages = $this->padAgesToCount($ages, $childCount);

        return new RoomOccupancyDto(max(1, $adults), $ages);
    }

    /**
     * @param RoomOccupancyDto[] $rooms
     */
    protected function redirectToIbeWithRooms(
        SiteConfigurationDto $config,
        string $arrival,
        string $departure,
        array $rooms,
        ?string $roomTypeId = null,
        ?string $culture = null,
        ?string $rateIds = null
    ): void {
        $arrivalDate = new DateTimeImmutable($arrival !== '' ? $arrival : 'tomorrow');
        $departureDate = new DateTimeImmutable($departure !== '' ? $departure : 'tomorrow +1 day');

        $url = $this->ibeUrlBuilder->buildForRooms(
            $config,
            $arrivalDate,
            $departureDate,
            $rooms,
            $culture,
            $roomTypeId,
            $rateIds
        );

        Typo3Adapter::redirect303($url);
    }

    /**
     * @return mixed
     */
    protected function jsonResponseOrNull(array $payload)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        if (method_exists($this, 'jsonResponse')) {
            $response = $this->jsonResponse($json);
            if (class_exists(PropagateResponseException::class)) {
                throw new PropagateResponseException($response, 1729162801);
            }

            return $response;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        exit;
    }

    /**
     * @return mixed
     */
    protected function htmlResponseOrNull(string $body = '')
    {
        if (method_exists($this, 'htmlResponse')) {
            if ($body !== '') {
                return $this->htmlResponse($body);
            }

            return $this->htmlResponse();
        }

        if ($body !== '') {
            echo $body;
            exit;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array{
     *     overviewDescriptionMode: string,
     *     overviewDescriptionLimit: int,
     *     overviewLayout: string
     * }
     */
    protected function parseOverviewDisplayExtras(array $settings): array
    {
        $overviewDescriptionMode = (string)($settings['overviewDescriptionMode'] ?? 'teaser');
        if (!in_array($overviewDescriptionMode, ['teaser', 'full'], true)) {
            $overviewDescriptionMode = 'teaser';
        }

        $overviewDescriptionLimit = max(50, min(2000, (int)($settings['overviewDescriptionLimit'] ?? 250)));

        $overviewLayout = (string)($settings['overviewLayout'] ?? 'grid');
        if (!in_array($overviewLayout, ['grid', 'list'], true)) {
            $overviewLayout = 'grid';
        }

        return [
            'overviewDescriptionMode' => $overviewDescriptionMode,
            'overviewDescriptionLimit' => $overviewDescriptionLimit,
            'overviewLayout' => $overviewLayout,
        ];
    }
}
