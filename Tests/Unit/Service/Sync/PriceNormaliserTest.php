<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Dto\CalendarDateDto;
use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Sync\PriceNormaliser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PriceNormaliserTest extends TestCase
{
    /** @var PriceNormaliser */
    private $subject;

    /** @var SiteConfigurationDto */
    private $config;

    protected function setUp(): void
    {
        $this->subject = new PriceNormaliser();
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

    public function testNormaliseRoomPricesUsesRoomTypeId(): void
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

        $rows = $this->subject->normaliseRoomPrices($this->config, $calendar, 'ROOM-A');

        self::assertCount(1, $rows);
        self::assertSame('ROOM-A', $rows[0]['room_type_id']);
        self::assertSame('', $rows[0]['rate_id']);
        self::assertSame(120.0, $rows[0]['from_price']);
        self::assertSame(1, $rows[0]['is_available']);
    }

    public function testNormalisePackagePricesUsesRateId(): void
    {
        $calendar = [
            new CalendarDateDto(
                new DateTimeImmutable('2026-06-01'),
                299.0,
                true,
                true,
                true,
                false,
                false,
                4,
                7,
                [4, 5],
                [4, 5]
            ),
        ];

        $rows = $this->subject->normalisePackagePrices($this->config, $calendar, 'PKG-1');

        self::assertCount(1, $rows);
        self::assertSame('', $rows[0]['room_type_id']);
        self::assertSame('PKG-1', $rows[0]['rate_id']);
        self::assertSame('4,5', $rows[0]['bookable_nights_with_packages']);
    }
}
