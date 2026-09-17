<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Service\BookingOffer\BookingOffersFetchService;
use Casablanca\CasablancaBooking\Service\Calendar\CalendarFetchService;
use DateTimeImmutable;
use TYPO3\CMS\Core\Http\PropagateResponseException;

/**
 * Shared fetchCalendar / fetchOffers actions for calendar-enabled plugins.
 */
trait CalendarApiActionsTrait
{
    /**
     * @return mixed
     */
    public function fetchCalendarAction()
    {
        $config = $this->resolveConfig();
        if ($config === null) {
            return $this->jsonResponseOrNull([
                'error' => 'not_configured',
            ]);
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $common = $this->parseCommonSettings($config);
        $params = array_merge(
            $this->request->getQueryParams(),
            (array)$this->request->getParsedBody()
        );

        $roomTypeId = $this->resolveCalendarRoomTypeId($params, $settings);
        $rateIds = $this->resolveCalendarRateIds($params);
        $windowDays = max(7, min(180, (int)($params['windowDays'] ?? $common['windowDays'])));

        $from = new DateTimeImmutable('today');
        $until = $from->modify(sprintf('+%d days', $windowDays - 1));

        try {
            $client = $this->apiClientFactory->createCalendarDatesClient($config);
            $payload = $this->calendarFetchService->fetchRange(
                $client,
                $config,
                $from,
                $until,
                $this->parseRoomOccupanciesFromRequest(),
                $roomTypeId !== '' ? $roomTypeId : null,
                $rateIds
            );

            return $this->jsonResponseOrNull($payload);
        } catch (PropagateResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $message = trim($e->getMessage());
            if ($message === '') {
                $message = 'Calendar fetch failed.';
            }

            return $this->jsonResponseOrNull([
                'error' => 'fetch_failed',
                'message' => $message,
            ]);
        }
    }

    /**
     * @return mixed
     */
    public function fetchOffersAction()
    {
        $config = $this->resolveConfig();
        if ($config === null) {
            return $this->jsonResponseOrNull([
                'error' => 'not_configured',
            ]);
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $params = array_merge(
            $this->request->getQueryParams(),
            (array)$this->request->getParsedBody()
        );

        $offerMode = $this->resolveCalendarOfferMode($params);
        if ($offerMode === BookingOffersFetchService::MODE_NONE) {
            return $this->jsonResponseOrNull([
                'offerMode' => BookingOffersFetchService::MODE_NONE,
                'offers' => [],
                'lowestTotalPrice' => null,
                'currency' => 'EUR',
            ]);
        }

        $arrival = trim((string)($params['arrival'] ?? ''));
        $departure = trim((string)($params['departure'] ?? ''));
        if ($arrival === '' || $departure === '' || $departure <= $arrival) {
            return $this->jsonResponseOrNull([
                'error' => 'invalid_dates',
                'message' => 'Arrival and departure dates are required.',
            ]);
        }

        $roomTypeId = $this->resolveCalendarRoomTypeId($params, $settings);
        $occupancies = $this->parseRoomOccupanciesFromRequest();
        $roomOccupancy = $occupancies[0];

        try {
            $client = $this->apiClientFactory->createBookingOffersClient($config);
            $payload = $this->bookingOffersFetchService->fetch(
                $client,
                $config,
                $arrival,
                $departure,
                $roomOccupancy,
                $offerMode,
                $roomTypeId !== '' ? $roomTypeId : null
            );

            return $this->jsonResponseOrNull($payload);
        } catch (PropagateResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $message = trim($e->getMessage());
            if ($message === '') {
                $message = 'Offers fetch failed.';
            }

            return $this->jsonResponseOrNull([
                'error' => 'fetch_failed',
                'message' => $message,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $settings
     */
    protected function resolveCalendarRoomTypeId(array $params, array $settings): string
    {
        $room = trim((string)($params['room'] ?? ''));
        if ($room !== '') {
            return $room;
        }

        foreach (['preselectRoomCategory', 'filterRoomType'] as $key) {
            $value = trim((string)($settings[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $params
     * @return string[]
     */
    protected function resolveCalendarRateIds(array $params): array
    {
        $raw = trim((string)($params['rateIds'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $raw));
        $rateIds = [];
        foreach ($parts as $part) {
            if ($part !== '') {
                $rateIds[] = $part;
            }
        }

        return $rateIds;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function resolveCalendarOfferMode(array $params): string
    {
        $fromRequest = trim((string)($params['offerMode'] ?? ''));
        if ($fromRequest !== '') {
            return BookingOffersFetchService::normaliseOfferMode($fromRequest);
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        foreach (['calendarOfferMode', 'detailCalendarOfferMode'] as $key) {
            $mode = trim((string)($settings[$key] ?? ''));
            if ($mode !== '') {
                return BookingOffersFetchService::normaliseOfferMode($mode);
            }
        }

        return BookingOffersFetchService::MODE_NONE;
    }

    protected function parseCalendarInitialMonthsFromSettings(string $settingsKey = 'calendarInitialMonths'): int
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $months = (int)($settings[$settingsKey] ?? 1);

        return in_array($months, [1, 2], true) ? $months : 1;
    }
}
