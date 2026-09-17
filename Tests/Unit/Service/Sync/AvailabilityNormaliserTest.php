<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Dto\CalendarDateDto;
use Casablanca\CasablancaBooking\Domain\Dto\InventoryCacheDto;
use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\RoomTypeDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Sync\AvailabilityNormaliser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AvailabilityNormaliserTest extends TestCase
{
    /** @var AvailabilityNormaliser */
    private $subject;

    /** @var SiteConfigurationDto */
    private $config;

    protected function setUp(): void
    {
        $this->subject = new AvailabilityNormaliser();
        $this->config = new SiteConfigurationDto(
            'hotel-example',
            'tenant-1',
            'context-1',
            'key',
            'https://api.casablanca.at',
            'https://ibe.casablanca.at',
            'de',
            365,
            31,
            100,
            new RoomOccupancyDto(2),
            'ibe'
        );
    }

    public function testProducesOneRowPerRoomTypePerDate(): void
    {
        $calendar = [
            new CalendarDateDto(
                new DateTimeImmutable('2026-06-01'),
                120.0,
                true,
                true,
                true,
                false,
                false,
                1,
                7,
                [1, 2, 3],
                []
            ),
        ];
        $inventory = [
            new InventoryCacheDto(new DateTimeImmutable('2026-06-01'), 'ROOM-A', 5),
            new InventoryCacheDto(new DateTimeImmutable('2026-06-01'), 'ROOM-B', 0),
        ];
        $rooms = [
            new RoomTypeDto('ROOM-A', 'Single', '', '', [], 'CO-1', 1, 1, 1, 2),
            new RoomTypeDto('ROOM-B', 'Suite', '', '', [], 'CO-1', 2, 2, 1, 3),
        ];

        $rows = $this->subject->normalise($this->config, $calendar, $inventory, $rooms);

        self::assertCount(2, $rows);
        self::assertSame('ROOM-A', $rows[0]['room_type_id']);
        self::assertSame(1, $rows[0]['is_available']);
        self::assertSame('ROOM-B', $rows[1]['room_type_id']);
        self::assertSame(0, $rows[1]['is_available']);
    }

    public function testStoresBookableNightsWithPackages(): void
    {
        $calendar = [
            new CalendarDateDto(
                new DateTimeImmutable('2026-06-01'),
                120.0,
                true,
                true,
                true,
                false,
                false,
                1,
                7,
                [1, 2],
                [4, 5]
            ),
        ];
        $inventory = [new InventoryCacheDto(new DateTimeImmutable('2026-06-01'), 'ROOM-A', 5)];
        $rooms = [new RoomTypeDto('ROOM-A', 'Single', '', '', [], 'CO-1', 1, 1, 1, 2)];

        $rows = $this->subject->normalise($this->config, $calendar, $inventory, $rooms);

        self::assertSame('4,5', $rows[0]['bookable_nights_with_packages']);
    }
}
