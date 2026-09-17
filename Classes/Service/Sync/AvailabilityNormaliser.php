<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Dto\CalendarDateDto;
use Casablanca\CasablancaBooking\Domain\Dto\InventoryCacheDto;
use Casablanca\CasablancaBooking\Domain\Dto\RoomTypeDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;

/**
 * Pure function: merges calendar dates with inventory and flattens to DB rows.
 */
final class AvailabilityNormaliser
{
    /**
     * @param CalendarDateDto[] $calendarDates
     * @param InventoryCacheDto[] $inventoryRows
     * @param RoomTypeDto[] $roomTypes
     * @return array<int, array<string, mixed>>
     */
    public function normalise(
        SiteConfigurationDto $config,
        array $calendarDates,
        array $inventoryRows,
        array $roomTypes
    ): array {
        $inventoryIndex = [];
        foreach ($inventoryRows as $inv) {
            $key = $inv->effectiveDate->format('Y-m-d') . '|' . $inv->roomTypeId;
            $inventoryIndex[$key] = $inv;
        }

        $calendarIndex = [];
        foreach ($calendarDates as $cal) {
            $calendarIndex[$cal->effectiveDate->format('Y-m-d')] = $cal;
        }

        $rows = [];
        foreach ($roomTypes as $rt) {
            foreach ($calendarIndex as $dateStr => $cal) {
                $invKey = $dateStr . '|' . $rt->id;
                $inv = isset($inventoryIndex[$invKey]) ? $inventoryIndex[$invKey] : null;

                $definitiveAvailable = $inv !== null ? $inv->definitiveAvailable : null;
                $hasRoomInventory = $definitiveAvailable !== null && $definitiveAvailable > 0;
                $isAvailable = $cal->isAvailable && $hasRoomInventory;

                $restrictions = [];
                if ($cal->minLengthOfStay > 1) {
                    $restrictions[] = 'Restriktionen';
                }
                if ($isAvailable && $cal->bookableNights === []) {
                    $restrictions[] = 'KeineBuchbaren';
                }
                if (!$isAvailable) {
                    $restrictions[] = 'NichtVerfügbar';
                }

                $row = [
                    'site_identifier' => $config->siteIdentifier,
                    'tenant_id' => $config->tenantId,
                    'ibe_context_id' => $config->urlFriendlyIbeContextId,
                    'room_type_id' => $rt->id,
                    'effective_date' => $dateStr,
                    'from_price' => $cal->fromPrice,
                    'currency' => 'EUR',
                    'is_available' => $isAvailable ? 1 : 0,
                    'is_arrival_allowed' => $cal->isArrivalAllowed ? 1 : 0,
                    'is_departure_allowed' => $cal->isDepartureAllowed ? 1 : 0,
                    'min_length_of_stay' => $cal->minLengthOfStay,
                    'max_length_of_stay' => $cal->maxLengthOfStay,
                    'bookable_nights' => implode(',', $cal->bookableNights),
                    'bookable_nights_with_packages' => implode(',', $cal->bookableNightsWithPackages),
                    'restrictions' => implode(',', $restrictions),
                    'previous_day_blocked' => $cal->isPreviousDayBlocked ? 1 : 0,
                    'next_day_blocked' => $cal->isNextDayBlocked ? 1 : 0,
                ];

                $row['data_hash'] = $this->hashRow($row);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hashRow(array $row): string
    {
        return hash('sha256', implode('|', [
            $row['from_price'] ?? 'null',
            $row['is_available'],
            $row['is_arrival_allowed'],
            $row['is_departure_allowed'],
            $row['min_length_of_stay'],
            $row['max_length_of_stay'],
            $row['bookable_nights'],
            $row['bookable_nights_with_packages'],
            $row['restrictions'],
            $row['previous_day_blocked'],
            $row['next_day_blocked'],
        ]));
    }
}
