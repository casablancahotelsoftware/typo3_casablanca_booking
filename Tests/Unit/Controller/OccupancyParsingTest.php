<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Controller;

use Casablanca\CasablancaBooking\Controller\SearchBarController;
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

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }
}
