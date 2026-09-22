<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\UrlBuilder;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IbeUrlBuilderTest extends TestCase
{
    /** @var IbeUrlBuilder */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new IbeUrlBuilder();
    }

    public function testBuildPathFullPath(): void
    {
        $config = $this->createConfig(IbeLinkStyle::FULL_PATH);

        self::assertSame(
            'https://booking.example.com/de/tenant-uuid/wellness-days',
            $this->subject->buildPath($config)
        );
    }

    public function testBuildPathCultureSpace(): void
    {
        $config = $this->createConfig(IbeLinkStyle::CULTURE_SPACE);

        self::assertSame(
            'https://booking.example.com/de/wellness-days',
            $this->subject->buildPath($config)
        );
    }

    public function testBuildPathCultureOnly(): void
    {
        $config = $this->createConfig(IbeLinkStyle::CULTURE_ONLY);

        self::assertSame(
            'https://booking.example.com/de',
            $this->subject->buildPath($config)
        );
    }

    public function testBuildPathNormalizesLegacyTenantOnly(): void
    {
        $config = $this->createConfig(IbeLinkStyle::LEGACY_TENANT_ONLY);

        self::assertSame(
            'https://booking.example.com/de/wellness-days',
            $this->subject->buildPath($config)
        );
    }

    public function testBuildPathUsesCultureOverride(): void
    {
        $config = $this->createConfig(IbeLinkStyle::CULTURE_SPACE);

        self::assertSame(
            'https://booking.example.com/en/wellness-days',
            $this->subject->buildPath($config, 'en')
        );
    }

    public function testBuildQueryParamsMinimalSingleRoom(): void
    {
        $params = $this->subject->buildQueryParams(
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-09'),
            [new RoomOccupancyDto(2)]
        );

        self::assertSame([
            'arrivalDate' => '2026-10-05',
            'departureDate' => '2026-10-09',
            'rooms_0__adults' => 2,
        ], $params);
    }

    public function testBuildQueryParamsMultiRoomWithChildren(): void
    {
        $params = $this->subject->buildQueryParams(
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-09'),
            [
                new RoomOccupancyDto(2, [5]),
                new RoomOccupancyDto(1),
            ]
        );

        self::assertSame(2, $params['numberOfRooms']);
        self::assertSame(2, $params['rooms_0__adults']);
        self::assertSame(1, $params['rooms_0__children']);
        self::assertSame(5, $params['rooms_0__children_0__age']);
        self::assertSame(1, $params['rooms_1__adults']);
        self::assertArrayNotHasKey('rooms_1__children', $params);
    }

    public function testBuildForRoomsUsesMinimalQueryString(): void
    {
        $config = $this->createConfig(IbeLinkStyle::FULL_PATH);
        $url = $this->subject->buildForRooms(
            $config,
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-09'),
            [new RoomOccupancyDto(2)]
        );

        self::assertSame(
            'https://booking.example.com/de/tenant-uuid/wellness-days?arrivalDate=2026-10-05&departureDate=2026-10-09&rooms_0__adults=2',
            $url
        );
    }

    private function createConfig(string $ibeLinkStyle): SiteConfigurationDto
    {
        return new SiteConfigurationDto(
            'hotel-example',
            'tenant-uuid',
            'wellness-days',
            'key',
            'https://api.casablanca.at',
            'https://booking.example.com',
            'de',
            365,
            31,
            100,
            new RoomOccupancyDto(2),
            'ibe',
            $ibeLinkStyle
        );
    }
}
