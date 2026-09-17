<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Domain\Model;

use Casablanca\CasablancaBooking\Domain\Model\Rate;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class RateTest extends TestCase
{
    /**
     * @dataProvider formattedCateringTypeProvider
     */
    public function testGetFormattedCateringType(string $raw, string $expected): void
    {
        $rate = new Rate();
        $this->setCateringType($rate, $raw);

        self::assertSame($expected, $rate->getFormattedCateringType());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function formattedCateringTypeProvider(): array
    {
        return [
            'empty' => ['', ''],
            'undefined' => ['Undefined', ''],
            'undefined lowercase' => ['undefined', ''],
            'halfboard' => ['Halfboard_modifiedAmericanPlan', 'Halfboard'],
            'bed and breakfast' => ['BedAndBreakfast', 'Bed and Breakfast'],
        ];
    }

    public function testGetDisplayTypeFallsBackToDayRateWhenCateringUnset(): void
    {
        $rate = new Rate();
        $this->setCateringType($rate, 'Undefined');

        self::assertSame('Day Rate', $rate->getDisplayType());
    }

    private function setCateringType(Rate $rate, string $cateringType): void
    {
        $property = new ReflectionProperty(Rate::class, 'cateringType');
        $property->setAccessible(true);
        $property->setValue($rate, $cateringType);
    }
}
