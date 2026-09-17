<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\BookingOffer;

use Casablanca\CasablancaBooking\Service\BookingOffer\BookingOffersFetchService;
use PHPUnit\Framework\TestCase;

final class BookingOffersFetchServiceTest extends TestCase
{
    public function testNormaliseOfferModeAcceptsKnownValues(): void
    {
        self::assertSame('none', BookingOffersFetchService::normaliseOfferMode('none'));
        self::assertSame('rates_only', BookingOffersFetchService::normaliseOfferMode('rates_only'));
        self::assertSame('packages_only', BookingOffersFetchService::normaliseOfferMode('packages_only'));
        self::assertSame(
            'packages_and_rates',
            BookingOffersFetchService::normaliseOfferMode('packages_and_rates')
        );
    }

    public function testNormaliseOfferModeFallsBackToNone(): void
    {
        self::assertSame('none', BookingOffersFetchService::normaliseOfferMode(''));
        self::assertSame('none', BookingOffersFetchService::normaliseOfferMode('invalid'));
    }
}
