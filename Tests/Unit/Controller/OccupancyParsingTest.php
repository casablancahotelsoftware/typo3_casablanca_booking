<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Controller;

use Casablanca\CasablancaBooking\Controller\SearchBarController;
use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use TYPO3\CMS\Extbase\Mvc\Request;

final class OccupancyParsingTest extends TestCase
{
    public function testParseRoomOccupanciesFromNestedRequest(): void
    {
        $controller = $this->getMockBuilder(SearchBarController::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getParsedBody')->willReturn([
            'rooms' => [
                ['adults' => '2', 'children' => '1', 'childAge' => ['5']],
                ['adults' => '1', 'children' => '0', 'childAge' => []],
            ],
        ]);

        $this->setProtectedProperty($controller, 'request', $request);

        $method = new ReflectionMethod(SearchBarController::class, 'parseRoomOccupanciesFromRequest');
        $method->setAccessible(true);
        $occupancies = $method->invoke($controller);

        self::assertCount(2, $occupancies);
        self::assertSame(2, $occupancies[0]->numberOfAdults);
        self::assertSame([5], $occupancies[0]->ageOfChildren);
        self::assertSame(1, $occupancies[1]->numberOfAdults);
        self::assertSame([], $occupancies[1]->ageOfChildren);
    }

    public function testParseIbeLinkTargetDefaultsToSelf(): void
    {
        $controller = $this->createControllerWithSettings([]);

        self::assertSame('_self', $this->invokeParseIbeLinkTarget($controller));
    }

    public function testParseIbeLinkTargetBlank(): void
    {
        $controller = $this->createControllerWithSettings(['ibeLinkTarget' => '_blank']);

        self::assertSame('_blank', $this->invokeParseIbeLinkTarget($controller));
        self::assertSame('_blank', $this->invokeParseIbeLinkTarget($controller, ['ibeLinkTarget' => '_blank']));
    }

    public function testParseIbeLinkTargetInvalidValueFallsBackToSelf(): void
    {
        $controller = $this->createControllerWithSettings(['ibeLinkTarget' => 'popup']);

        self::assertSame('_self', $this->invokeParseIbeLinkTarget($controller));
    }

    public function testResolveEffectiveCultureUsesSiteDefaultWhenFlexFormAuto(): void
    {
        $controller = $this->createControllerWithSettings(['language' => '']);
        $config = new SiteConfigurationDto(
            'hotel-example',
            'tenant-uuid',
            'wellness-days',
            'key',
            'https://api.casablanca.at',
            'https://booking.example.com',
            'en',
            365,
            31,
            100,
            new RoomOccupancyDto(2)
        );

        self::assertSame('en', $this->invokeResolveEffectiveCulture($controller, $config));
    }

    public function testEnsureDepartureAfterArrivalAddsOneNightWhenSameDay(): void
    {
        $controller = $this->getMockBuilder(SearchBarController::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(SearchBarController::class, 'ensureDepartureAfterArrival');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '2026-10-05', '2026-10-05');

        self::assertSame('2026-10-05', $result['arrival']);
        self::assertSame('2026-10-06', $result['departure']);
    }

    public function testEnsureDepartureAfterArrivalKeepsValidRange(): void
    {
        $controller = $this->getMockBuilder(SearchBarController::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(SearchBarController::class, 'ensureDepartureAfterArrival');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '2026-10-05', '2026-10-09');

        self::assertSame('2026-10-05', $result['arrival']);
        self::assertSame('2026-10-09', $result['departure']);
    }

    public function testEnsureDepartureAfterArrivalFillsMissingDeparture(): void
    {
        $controller = $this->getMockBuilder(SearchBarController::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(SearchBarController::class, 'ensureDepartureAfterArrival');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '2026-10-05', '');

        self::assertSame('2026-10-05', $result['arrival']);
        self::assertSame('2026-10-06', $result['departure']);
    }

    public function testResolveRequestDateParamReadsPlainQueryParameter(): void
    {
        $controller = $this->getMockBuilder(SearchBarController::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this->createMock(Request::class);
        $request->method('getQueryParams')->willReturn([
            'arrival' => '2026-10-05',
            'departure' => '2026-10-09',
        ]);
        $request->method('getParsedBody')->willReturn([]);

        $this->setProtectedProperty($controller, 'request', $request);

        $method = new ReflectionMethod(SearchBarController::class, 'resolveRequestDateParam');
        $method->setAccessible(true);

        self::assertSame(
            '2026-10-05',
            $method->invoke($controller, '', 'arrival')
        );
        self::assertSame(
            '2026-10-09',
            $method->invoke($controller, '', 'departure')
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function createControllerWithSettings(array $settings): SearchBarController
    {
        $controller = $this->getMockBuilder(SearchBarController::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->setProtectedProperty($controller, 'settings', $settings);

        return $controller;
    }

    /**
     * @param array<string, mixed>|null $overrideSettings
     */
    private function invokeParseIbeLinkTarget(SearchBarController $controller, ?array $overrideSettings = null): string
    {
        $method = new ReflectionMethod(SearchBarController::class, 'parseIbeLinkTarget');
        $method->setAccessible(true);

        return $method->invoke($controller, $overrideSettings);
    }

    private function invokeResolveEffectiveCulture(
        SearchBarController $controller,
        SiteConfigurationDto $config
    ): string {
        $method = new ReflectionMethod(SearchBarController::class, 'resolveEffectiveCulture');
        $method->setAccessible(true);

        return $method->invoke($controller, $config, ['language' => '']);
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }
}
