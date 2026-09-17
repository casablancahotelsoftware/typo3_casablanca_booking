<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Service\Api\ApiClientFactory;
use Casablanca\CasablancaBooking\Service\Calendar\CalendarFetchService;
use Casablanca\CasablancaBooking\Service\BookingOffer\BookingOffersFetchService;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Availability calendar widget with live API fetch on demand.
 */
class BookingWidgetController extends AbstractWidgetController
{
    use CalendarApiActionsTrait;
    use CalendarPresentationTrait;

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
        RoomTypeRepository $roomTypeRepository,
        CalendarFetchService $calendarFetchService,
        BookingOffersFetchService $bookingOffersFetchService
    ) {
        parent::__construct($siteFinder, $apiClientFactory, $ibeUrlBuilder);
        $this->roomTypeRepository = $roomTypeRepository;
        $this->calendarFetchService = $calendarFetchService;
        $this->bookingOffersFetchService = $bookingOffersFetchService;
    }

    /**
     * @return mixed
     */
    public function showAction()
    {
        $this->includeFrontendAssets();

        $config = $this->resolveConfig();
        if ($config === null) {
            $this->view->assign('error', 'not_configured');

            return $this->htmlResponseOrNull();
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $common = $this->parseCommonSettings($config);

        $preselectedRoomTypeId = trim((string)($settings['preselectRoomCategory'] ?? ''));
        $from = new DateTimeImmutable('today');
        $until = $from->modify(sprintf('+%d days', $common['windowDays'] - 1));

        $enquiry = $this->parseEnquirySettings();
        $calendarOfferMode = $this->resolveCalendarOfferMode([]);

        $preselectedRoomType = null;
        if ($preselectedRoomTypeId !== '') {
            $preselectedRoomType = $this->roomTypeRepository->findOneBySiteAndRoomTypeId(
                $config->siteIdentifier,
                $preselectedRoomTypeId
            );
        }

        $this->tagPageCache($config, $preselectedRoomTypeId);

        $calendarVars = $this->buildCalendarPresentationVariables(
            $config,
            $common,
            'BookingWidget',
            'Widget',
            Typo3Adapter::CALENDAR_PAGE_TYPE,
            [
                'calendarOfferMode' => $calendarOfferMode,
                'calendarInitialMonths' => $this->parseCalendarInitialMonthsFromSettings(),
                'preselectedRoomTypeId' => $preselectedRoomTypeId,
                'preselectedRoomType' => $preselectedRoomType,
                'showCalendarLegend' => true,
                'showCalendarHeading' => true,
                'showEnquiryButton' => $enquiry['showEnquiryButton'],
                'enquiryUrl' => $enquiry['enquiryUrl'],
                'enquiryLinkTarget' => $enquiry['enquiryLinkTarget'],
            ]
        );

        $this->view->assignMultiple(array_merge([
            'config' => $config,
            'preselectedRoomType' => $preselectedRoomType,
            'from' => $from,
            'until' => $until,
        ], $calendarVars));

        return $this->htmlResponseOrNull();
    }

    /**
     * @return mixed
     */
    public function redirectAction(
        string $arrival = '',
        string $departure = '',
        string $room = '',
        string $rateIds = ''
    ) {
        $config = $this->resolveConfig();
        if ($config === null) {
            return $this->htmlResponseOrNull('Site not configured for CASABLANCA.');
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $culture = trim((string)($settings['language'] ?? $config->defaultCulture));
        if ($culture === '') {
            $culture = null;
        }

        $params = array_merge(
            $this->request->getQueryParams(),
            (array)$this->request->getParsedBody()
        );
        $resolvedRateIds = trim($rateIds);
        if ($resolvedRateIds === '') {
            $resolvedRateIds = trim((string)($params['rateIds'] ?? ''));
        }

        $this->redirectToIbeWithRooms(
            $config,
            $arrival,
            $departure,
            $this->parseRoomOccupanciesFromRequest(),
            $room !== '' ? $room : null,
            $culture,
            $resolvedRateIds !== '' ? $resolvedRateIds : null
        );

        return null;
    }

    /**
     * @return array{showEnquiryButton: bool, enquiryUrl: string, enquiryLinkTarget: string}
     */
    private function parseEnquirySettings(): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $show = (bool)($settings['showEnquiryButton'] ?? false);
        $url = trim((string)($settings['enquiryUrl'] ?? ''));
        $target = trim((string)($settings['enquiryLinkTarget'] ?? '_self'));

        return [
            'showEnquiryButton' => $show && $url !== '',
            'enquiryUrl' => $url,
            'enquiryLinkTarget' => in_array($target, ['_self', '_blank'], true) ? $target : '_self',
        ];
    }
}
