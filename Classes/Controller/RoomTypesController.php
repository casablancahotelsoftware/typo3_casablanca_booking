<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Model\RoomType;
use Casablanca\CasablancaBooking\Domain\Repository\AvailabilityRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use Casablanca\CasablancaBooking\Service\Api\ApiClientFactory;
use Casablanca\CasablancaBooking\Service\BookingOffer\BookingOffersFetchService;
use Casablanca\CasablancaBooking\Service\Calendar\CalendarFetchService;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Room type cards with from-price in a configurable window.
 */
class RoomTypesController extends AbstractWidgetController
{
    use CalendarApiActionsTrait;
    use CalendarPresentationTrait;
    use DetailCalendarTrait;

    private const DISPLAY_MODE_OVERVIEW = 'overview';
    private const DISPLAY_MODE_DETAIL = 'detail';
    private const LINK_TYPE_BOOK = 'book';
    private const LINK_TYPE_DETAILS = 'details';

    /** @var AvailabilityRepository */
    private $availabilityRepository;

    /** @var RoomTypeRepository */
    private $roomTypeRepository;

    /** @var CalendarFetchService */
    protected $calendarFetchService;

    /** @var BookingOffersFetchService */
    protected $bookingOffersFetchService;

    public function __construct(
        SiteFinder $siteFinder,
        ApiClientFactory $apiClientFactory,
        IbeUrlBuilder $ibeUrlBuilder,
        AvailabilityRepository $availabilityRepository,
        RoomTypeRepository $roomTypeRepository,
        CalendarFetchService $calendarFetchService,
        BookingOffersFetchService $bookingOffersFetchService
    ) {
        parent::__construct($siteFinder, $apiClientFactory, $ibeUrlBuilder);
        $this->availabilityRepository = $availabilityRepository;
        $this->roomTypeRepository = $roomTypeRepository;
        $this->calendarFetchService = $calendarFetchService;
        $this->bookingOffersFetchService = $bookingOffersFetchService;
    }

    /**
     * @return mixed
     */
    public function showAction(string $roomSlug = '')
    {
        $this->includeFrontendAssets();

        $config = $this->resolveConfig();
        if ($config === null) {
            $this->view->assign('error', 'not_configured');

            return $this->htmlResponseOrNull();
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $displaySettings = $this->parseDisplaySettings($settings);
        $common = $this->parseCommonSettings($config);
        $stayNights = max(1, min(30, (int)($settings['stayNights'] ?? 7)));

        $from = new DateTimeImmutable('today');
        $until = $from->modify(sprintf('+%d days', $common['windowDays'] - 1));
        $departure = $from->modify(sprintf('+%d days', $stayNights));

        $resolvedRoomSlug = trim($roomSlug);
        if ($resolvedRoomSlug === '') {
            $resolvedRoomSlug = $this->resolveRoomSlugFromRequest();
        }

        $roomTypes = $this->resolveRoomTypes(
            $config,
            $settings,
            $displaySettings['displayMode'],
            $resolvedRoomSlug
        );

        if ($displaySettings['displayMode'] === self::DISPLAY_MODE_DETAIL && $roomTypes === []) {
            $this->view->assign('error', 'room_not_found');
            $this->assignCommonViewVariables($config, $common, $displaySettings, $stayNights, $from, $until, []);

            return $this->htmlResponseOrNull();
        }

        $detailPageUid = $this->parseDetailPageUid($settings);
        $occupancy = $this->buildOccupancy(
            $common['adults'],
            $common['defaultChildrenCount'],
            $common['childrenAges']
        );

        $cards = [];
        foreach ($roomTypes as $roomType) {
            $cheapest = $this->availabilityRepository->findCheapestPrice(
                $config->siteIdentifier,
                $from,
                $until,
                $roomType->getRoomTypeId()
            );

            $ibeUrl = $this->ibeUrlBuilder->build(
                $config,
                $from,
                $departure,
                $occupancy,
                $roomType->getRoomTypeId(),
                $common['culture']
            );

            $detailUrl = '';
            if (
                $displaySettings['displayMode'] === self::DISPLAY_MODE_OVERVIEW
                && $displaySettings['cardLinkType'] === self::LINK_TYPE_DETAILS
                && $detailPageUid > 0
                && $roomType->getSlug() !== ''
            ) {
                $detailUrl = $this->buildDetailUrl($detailPageUid, $roomType->getSlug());
            }

            $cards[] = [
                'roomType' => $roomType,
                'fromPrice' => $cheapest !== null ? ($cheapest['price'] ?? null) : null,
                'currency' => $cheapest !== null ? ($cheapest['currency'] ?? 'EUR') : 'EUR',
                'ibeUrl' => $ibeUrl,
                'detailUrl' => $detailUrl,
            ];
        }

        $cacheRoomId = $roomTypes !== [] ? $roomTypes[0]->getRoomTypeId() : '';
        $this->tagPageCache($config, $cacheRoomId);

        $this->assignCommonViewVariables(
            $config,
            $common,
            $displaySettings,
            $stayNights,
            $from,
            $until,
            $cards
        );

        if ($displaySettings['displayMode'] === self::DISPLAY_MODE_DETAIL) {
            $this->assignDetailCalendarVariables($config, $common, $settings, $cards, false);
        }

        return $this->htmlResponseOrNull();
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array{
     *     displayMode: string,
     *     showName: bool,
     *     showDescription: bool,
     *     showPrice: bool,
     *     showImage: bool,
     *     cardLinkType: string
     * }
     */
    private function parseDisplaySettings(array $settings): array
    {
        $displayMode = (string)($settings['displayMode'] ?? self::DISPLAY_MODE_OVERVIEW);
        if (!in_array($displayMode, [self::DISPLAY_MODE_OVERVIEW, self::DISPLAY_MODE_DETAIL], true)) {
            $displayMode = self::DISPLAY_MODE_OVERVIEW;
        }

        $cardLinkType = (string)($settings['cardLinkType'] ?? self::LINK_TYPE_BOOK);
        if (!in_array($cardLinkType, [self::LINK_TYPE_BOOK, self::LINK_TYPE_DETAILS], true)) {
            $cardLinkType = self::LINK_TYPE_BOOK;
        }

        return [
            'displayMode' => $displayMode,
            'showName' => $this->isSettingEnabled($settings, 'showName'),
            'showDescription' => $this->isSettingEnabled($settings, 'showDescription'),
            'showPrice' => $this->isSettingEnabled($settings, 'showPrice'),
            'showImage' => $this->isSettingEnabled($settings, 'showImage'),
            'cardLinkType' => $cardLinkType,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function isSettingEnabled(array $settings, string $key): bool
    {
        if (!array_key_exists($key, $settings)) {
            return true;
        }

        $value = $settings[$key];

        return $value !== '' && $value !== '0' && $value !== 0 && $value !== false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function parseDetailPageUid(array $settings): int
    {
        $raw = $settings['detailPageUid'] ?? '';
        if (is_array($raw)) {
            $raw = $raw[0] ?? '';
        }

        if (is_string($raw) && strpos($raw, ',') !== false) {
            $raw = explode(',', $raw)[0];
        }

        return max(0, (int)$raw);
    }

    private function resolveRoomSlugFromRequest(): string
    {
        if ($this->request->hasArgument('roomSlug')) {
            return trim((string)$this->request->getArgument('roomSlug'));
        }

        $pluginSignature = Typo3Adapter::getPluginSignature('CasablancaBooking', 'RoomTypes');
        $params = (array)($this->request->getQueryParams()[$pluginSignature] ?? []);

        return trim((string)($params['roomSlug'] ?? ''));
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return RoomType[]
     */
    private function resolveRoomTypes(
        SiteConfigurationDto $config,
        array $settings,
        string $displayMode,
        string $roomSlug
    ): array {
        if ($displayMode === self::DISPLAY_MODE_DETAIL) {
            if ($roomSlug === '') {
                return [];
            }

            $room = $this->roomTypeRepository->findOneBySiteAndSlug($config->siteIdentifier, $roomSlug);

            return $room !== null ? [$room] : [];
        }

        $roomTypes = $this->roomTypeRepository->findForSite($config->siteIdentifier);
        $filterRoomTypeId = trim((string)($settings['filterRoomType'] ?? ''));
        if ($filterRoomTypeId === '') {
            return $roomTypes;
        }

        return array_values(array_filter(
            $roomTypes,
            static function (RoomType $roomType) use ($filterRoomTypeId): bool {
                return $roomType->getRoomTypeId() === $filterRoomTypeId;
            }
        ));
    }

    private function buildDetailUrl(int $detailPageUid, string $roomSlug): string
    {
        if ($detailPageUid <= 0 || $roomSlug === '') {
            return '';
        }

        try {
            $url = $this->uriBuilder
                ->reset()
                ->setTargetPageUid($detailPageUid)
                ->setCreateAbsoluteUri(false)
                ->uriFor(
                    'show',
                    ['roomSlug' => $roomSlug],
                    'RoomTypes',
                    'CasablancaBooking',
                    'RoomTypes'
                );

            return $url !== '' ? $url : $this->buildDetailUrlFallback($detailPageUid, $roomSlug);
        } catch (\Throwable) {
            return $this->buildDetailUrlFallback($detailPageUid, $roomSlug);
        }
    }

    private function buildDetailUrlFallback(int $detailPageUid, string $roomSlug): string
    {
        $pluginSignature = Typo3Adapter::getPluginSignature('CasablancaBooking', 'RoomTypes');

        return (string)$this->uriBuilder
            ->reset()
            ->setTargetPageUid($detailPageUid)
            ->setCreateAbsoluteUri(false)
            ->setArguments([
                $pluginSignature => [
                    'roomSlug' => $roomSlug,
                ],
            ])
            ->build();
    }

    /**
     * @param array<string, mixed> $common
     * @param array<int, array<string, mixed>> $cards
     */
    private function assignCommonViewVariables(
        SiteConfigurationDto $config,
        array $common,
        array $displaySettings,
        int $stayNights,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        array $cards
    ): void {
        $this->view->assignMultiple([
            'config' => $config,
            'cards' => $cards,
            'culture' => $common['culture'],
            'defaultAdults' => $common['adults'],
            'defaultChildrenCount' => $common['defaultChildrenCount'],
            'windowDays' => $common['windowDays'],
            'from' => $from,
            'until' => $until,
            'displayMode' => $displaySettings['displayMode'],
            'showName' => $displaySettings['showName'],
            'showDescription' => $displaySettings['showDescription'],
            'showPrice' => $displaySettings['showPrice'],
            'showImage' => $displaySettings['showImage'],
            'cardLinkType' => $displaySettings['cardLinkType'],
            'stayNights' => $stayNights,
            'ibeLinkTarget' => $this->parseIbeLinkTarget(),
        ]);
    }
}
