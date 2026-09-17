<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Controller;

use Casablanca\CasablancaBooking\Controller\PackagesController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PackagesStayFilterTest extends TestCase
{
    /**
     * @dataProvider packageSupportsStayLengthProvider
     *
     * @param int[] $bookableNights
     */
    public function testPackageSupportsStayLength(array $bookableNights, int $stayNights, bool $expected): void
    {
        $controller = $this->createPartialMock(PackagesController::class, []);
        $method = new ReflectionMethod(PackagesController::class, 'packageSupportsStayLength');
        $method->setAccessible(true);

        self::assertSame(
            $expected,
            $method->invoke($controller, $bookableNights, $stayNights)
        );
    }

    /**
     * @return array<string, array{0: int[], 1: int, 2: bool}>
     */
    public static function packageSupportsStayLengthProvider(): array
    {
        return [
            'empty bookable nights allows all' => [[], 7, true],
            'matching stay length' => [[3, 7, 10], 7, true],
            'no matching stay length' => [[3, 5], 7, false],
        ];
    }
}
