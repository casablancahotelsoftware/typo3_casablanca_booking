<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\UrlBuilder;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
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
