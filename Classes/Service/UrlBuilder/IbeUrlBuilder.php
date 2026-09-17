<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\UrlBuilder;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use DateTimeInterface;

/**
 * Builds CASABLANCA IBE v2 deep-links for PCI-safe handover.
 *
 * @see https://docs.casablanca.at/cloud/module/ibev2/url_params
 */
class IbeUrlBuilder
{
    /**
     * @param string|null $culture null → site default
     */
    public function build(
        SiteConfigurationDto $config,
        DateTimeInterface $arrival,
        DateTimeInterface $departure,
        RoomOccupancyDto $occupancy,
        ?string $preselectedRoomTypeId = null,
        ?string $culture = null,
        ?string $rateIds = null
    ): string {
        return $this->buildForRooms(
            $config,
            $arrival,
            $departure,
            [$occupancy],
            $culture,
            $preselectedRoomTypeId,
            $rateIds
        );
    }

    /**
     * @param RoomOccupancyDto[] $rooms
     */
    public function buildForRooms(
        SiteConfigurationDto $config,
        DateTimeInterface $arrival,
        DateTimeInterface $departure,
        array $rooms,
        ?string $culture = null,
        ?string $preselectedRoomTypeId = null,
        ?string $rateIds = null
    ): string {
        if ($rooms === []) {
            $rooms = [new RoomOccupancyDto(1)];
        }

        $params = [
            'arrivalDate' => $arrival->format('Y-m-d'),
            'departureDate' => $departure->format('Y-m-d'),
            'numberOfRooms' => count($rooms),
        ];

        foreach ($rooms as $index => $room) {
            $params['rooms_' . $index . '__adults'] = $room->numberOfAdults;
            $params['rooms_' . $index . '__children'] = $room->getNumberOfChildren();

            foreach ($room->ageOfChildren as $childIndex => $age) {
                $clamped = max(0, min(17, (int)$age));
                $params['rooms_' . $index . '__children_' . $childIndex . '__age'] = $clamped;
            }
        }

        if ($rateIds !== null && $rateIds !== '') {
            $params['rateIds'] = $rateIds;
        }

        if ($preselectedRoomTypeId !== null && $preselectedRoomTypeId !== '') {
            $params['roomTypeIds'] = $preselectedRoomTypeId;
        }

        $path = $this->buildPath($config, $culture);

        return $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Returns the IBE base path without query string.
     */
    public function buildPath(SiteConfigurationDto $config, ?string $culture = null): string
    {
        $cultureSegment = $culture !== null && $culture !== ''
            ? $culture
            : $config->defaultCulture;

        $base = rtrim($config->ibeBaseUrl, '/');
        $style = IbeLinkStyle::normalize($config->ibeLinkStyle);

        $segments = [$base, rawurlencode($cultureSegment)];

        if ($style === IbeLinkStyle::CULTURE_ONLY) {
            return implode('/', $segments);
        }

        $segments[] = rawurlencode($config->tenantId);

        if ($style === IbeLinkStyle::TENANT_ONLY) {
            return implode('/', $segments);
        }

        $segments[] = rawurlencode($config->urlFriendlyIbeContextId);

        return implode('/', $segments);
    }
}
