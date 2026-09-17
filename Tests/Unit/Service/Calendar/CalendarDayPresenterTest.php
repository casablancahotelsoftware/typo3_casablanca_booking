<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\Calendar;

use Casablanca\CasablancaBooking\Domain\Dto\CalendarDateDto;
use Casablanca\CasablancaBooking\Service\Calendar\CalendarDayPresenter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CalendarDayPresenterTest extends TestCase
{
    public function testPresentsAvailableDay(): void
    {
        $subject = new CalendarDayPresenter();
        $dto = new CalendarDateDto(
            new DateTimeImmutable('2026-06-01'),
            99.0,
            true,
            true,
            true,
            false,
            false,
            1,
            7,
            [1, 2],
            []
        );

        $day = $subject->present($dto, 'ROOM-A');

        self::assertSame('2026-06-01', $day['date']);
        self::assertSame(99.0, $day['fromPrice']);
        self::assertTrue($day['isAvailable']);
        self::assertSame('cb-state-available', $day['calendarStateClass']);
        self::assertSame('ROOM-A', $day['roomTypeId']);
    }

    public function testPresentsUnavailableDay(): void
    {
        $subject = new CalendarDayPresenter();
        $dto = new CalendarDateDto(
            new DateTimeImmutable('2026-06-02'),
            null,
            false,
            false,
            false,
            false,
            false,
            1,
            7,
            [],
            []
        );

        $day = $subject->present($dto);

        self::assertSame('cb-state-unavailable', $day['calendarStateClass']);
        self::assertFalse($day['isAvailable']);
    }
}
