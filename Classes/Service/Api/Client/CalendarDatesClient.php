<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api\Client;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\CalendarDateDto;
use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Api\CasablancaHttpClient;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Endpoint wrapper for POST /calendar-dates.
 */
final class CalendarDatesClient
{
    /** @var CasablancaHttpClient */
    private $http;

    /** @var SiteConfigurationDto */
    private $config;

    public function __construct(CasablancaHttpClient $http, SiteConfigurationDto $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * @param RoomOccupancyDto[] $roomOccupancies
     * @param array<string, array<int, string>>|null $stayFilter
     * @return CalendarDateDto[]
     */
    public function fetchMonth(
        DateTimeInterface $monthAnchor,
        array $roomOccupancies,
        ?array $stayFilter = null
    ): array {
        $occupancies = [];
        foreach ($roomOccupancies as $occupancy) {
            $occupancies[] = $occupancy->toArray();
        }

        $body = [
            'roomOccupancies' => $occupancies,
        ];
        if ($stayFilter !== null) {
            $body['stayFilter'] = $stayFilter;
        }

        $response = $this->http->postJson('/calendar-dates', $body, [
            'month' => $monthAnchor->format('Y-m-01'),
            'culture' => $this->config->defaultCulture,
        ]);

        $items = Typo3Adapter::isArrayList($response)
            ? $response
            : (array)($response['values'] ?? []);

        $result = [];
        foreach ($items as $item) {
            $result[] = CalendarDateDto::fromArray((array)$item);
        }

        return $result;
    }

    /**
     * @param RoomOccupancyDto[] $roomOccupancies
     * @return iterable<int, CalendarDateDto>
     */
    public function streamRange(
        DateTimeInterface $from,
        DateTimeInterface $until,
        array $roomOccupancies,
        ?array $stayFilter = null
    ): iterable {
        $cursor = new DateTimeImmutable($from->format('Y-m-01'));
        $end = new DateTimeImmutable($until->format('Y-m-01'));

        while ($cursor <= $end) {
            foreach ($this->fetchMonth($cursor, $roomOccupancies, $stayFilter) as $dto) {
                yield $dto;
            }
            $cursor = $cursor->modify('+1 month');
        }
    }
}
