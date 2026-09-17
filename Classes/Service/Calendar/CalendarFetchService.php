<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Calendar;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Api\Client\CalendarDatesClient;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Fetches live calendar dates from the CASABLANCA API for frontend widgets.
 */
final class CalendarFetchService
{
    /** @var CalendarDayPresenter */
    private $presenter;

    public function __construct(CalendarDayPresenter $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * @param RoomOccupancyDto[] $roomOccupancies
     * @return array{months: array<string, array<int, array<string, mixed>>>}
     */
    /**
     * @param string[] $rateIds
     * @return array{months: array<string, array<int, array<string, mixed>>>}
     */
    public function fetchRange(
        CalendarDatesClient $client,
        SiteConfigurationDto $config,
        DateTimeInterface $from,
        DateTimeInterface $until,
        array $roomOccupancies,
        ?string $preselectedRoomTypeId = null,
        array $rateIds = []
    ): array {
        if ($roomOccupancies === []) {
            $roomOccupancies = [$config->defaultOccupancy];
        }

        $stayFilter = $this->buildStayFilter($preselectedRoomTypeId, $rateIds);

        $months = [];
        $monthCursor = new DateTimeImmutable($from->format('Y-m-01'));
        $monthEnd = new DateTimeImmutable($until->format('Y-m-01'));
        $untilDate = $until->format('Y-m-d');

        while ($monthCursor <= $monthEnd) {
            $calendarDates = $client->fetchMonth($monthCursor, $roomOccupancies, $stayFilter);
            $monthKey = $monthCursor->format('Y-m');

            foreach ($calendarDates as $cal) {
                $dateStr = $cal->effectiveDate->format('Y-m-d');
                if ($dateStr < $from->format('Y-m-d') || $dateStr > $untilDate) {
                    continue;
                }
                $months[$monthKey][] = $this->presenter->present(
                    $cal,
                    $preselectedRoomTypeId ?? ''
                );
            }

            $monthCursor = $monthCursor->modify('+1 month');
        }

        ksort($months);

        return ['months' => $months];
    }

    /**
     * @param string[] $rateIds
     * @return array<string, mixed>|null
     */
    private function buildStayFilter(?string $roomTypeId, array $rateIds): ?array
    {
        $roomTypeId = $roomTypeId !== null ? trim($roomTypeId) : '';
        $rateIds = array_values(array_filter(array_map('trim', $rateIds), static function (string $id): bool {
            return $id !== '';
        }));

        if ($roomTypeId === '' && $rateIds === []) {
            return null;
        }

        return [
            'companyIdentifiers' => [],
            'roomTypeIds' => $roomTypeId !== '' ? [$roomTypeId] : [],
            'rateIds' => $rateIds,
        ];
    }
}
