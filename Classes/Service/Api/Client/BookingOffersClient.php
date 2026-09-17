<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api\Client;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Api\CasablancaHttpClient;

/**
 * Endpoint wrapper for POST /booking-offers/{arrivalDate}/{departureDate}.
 */
final class BookingOffersClient
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
     * Creates a booking offer for the given stay window and occupancy.
     *
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
    ): array {
        $body = [
            'selectionCriteria' => $selectionCriteria,
            'roomOccupancy' => $roomOccupancy->toArray(),
        ];
        if ($stayFilter !== []) {
            $body['stayFilter'] = $stayFilter;
        }

        $path = sprintf(
            '/booking-offers/%s/%s',
            rawurlencode($arrivalDate),
            rawurlencode($departureDate)
        );

        return $this->http->postJson($path, $body, [
            'culture' => $this->config->defaultCulture,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getById(string $bookingOfferId): array
    {
        return $this->http->getJson(
            '/booking-offers/' . rawurlencode($bookingOfferId),
            ['culture' => $this->config->defaultCulture]
        );
    }
}
