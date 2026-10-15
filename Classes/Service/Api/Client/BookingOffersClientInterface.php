<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api\Client;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;

/**
 * Booking-offers API operations used by the calendar offers fetch flow.
 */
interface BookingOffersClientInterface
{
    /**
     * @param array<string, mixed> $selectionCriteria
     * @param array<string, mixed> $stayFilter
     * @return array<string, mixed>
     */
    public function create(
        string $arrivalDate,
        string $departureDate,
        RoomOccupancyDto $roomOccupancy,
        array $selectionCriteria = [],
        array $stayFilter = []
    ): array;

    /**
     * @param array<string, mixed> $selectionCriteria
     * @return array<string, mixed>
     */
    public function recalculate(
        string $bookingOfferId,
        string $arrivalDate,
        string $departureDate,
        RoomOccupancyDto $roomOccupancy,
        array $selectionCriteria = []
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getById(string $bookingOfferId): array;
}
