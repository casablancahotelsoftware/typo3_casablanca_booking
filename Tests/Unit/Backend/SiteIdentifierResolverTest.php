<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Backend;

use Casablanca\CasablancaBooking\Backend\SiteIdentifierResolver;
use Casablanca\CasablancaBooking\Domain\Model\Configuration;
use Casablanca\CasablancaBooking\Domain\Repository\ConfigurationRepository;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

final class SiteIdentifierResolverTest extends TestCase
{
    public function testResolvesFromSiteObject(): void
    {
        $site = $this->createMock(Site::class);
        $site->method('getIdentifier')->willReturn('hotel-example');

        $subject = new SiteIdentifierResolver(
            $this->createMock(SiteFinder::class),
            $this->createMock(ConfigurationRepository::class)
        );

        self::assertSame(
            'hotel-example',
            $subject->resolveFromItemsProcParams(['site' => $site])
        );
    }

    public function testIgnoresNullSiteObject(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->with(42)->willReturn(
            $this->createConfiguredMock(Site::class, [
                'getIdentifier' => 'from-effective-pid',
            ])
        );

        $subject = new SiteIdentifierResolver(
            $siteFinder,
            $this->createMock(ConfigurationRepository::class)
        );

        self::assertSame(
            'from-effective-pid',
            $subject->resolveFromItemsProcParams([
                'site' => new NullSite(),
                'effectivePid' => 42,
            ])
        );
    }

    public function testResolvesFromFlexParentDatabaseRowPid(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->with(7)->willReturn(
            $this->createConfiguredMock(Site::class, [
                'getIdentifier' => 'flex-parent-site',
            ])
        );

        $subject = new SiteIdentifierResolver(
            $siteFinder,
            $this->createMock(ConfigurationRepository::class)
        );

        self::assertSame(
            'flex-parent-site',
            $subject->resolveFromItemsProcParams([
                'flexParentDatabaseRow' => ['pid' => 7],
                'row' => ['pid' => 0],
            ])
        );
    }

    public function testUsesSingleConfiguredSiteFallback(): void
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getSiteIdentifier')->willReturn('only-site');

        $configurationRepository = $this->createMock(ConfigurationRepository::class);
        $configurationRepository->method('findAll')->willReturn([$configuration]);

        $subject = new SiteIdentifierResolver(
            $this->createMock(SiteFinder::class),
            $configurationRepository
        );

        self::assertSame(
            'only-site',
            $subject->resolveFromItemsProcParams(['row' => ['pid' => 0]])
        );
    }

    public function testReturnsNullWhenNothingMatches(): void
    {
        $configurationRepository = $this->createMock(ConfigurationRepository::class);
        $configurationRepository->method('findAll')->willReturn([]);

        $subject = new SiteIdentifierResolver(
            $this->createMock(SiteFinder::class),
            $configurationRepository
        );

        self::assertNull($subject->resolveFromItemsProcParams(['row' => ['pid' => 0]]));
    }

    public function testHasCasablancaConfigurationFromRepository(): void
    {
        $configurationRepository = $this->createMock(ConfigurationRepository::class);
        $configurationRepository->method('findBySiteIdentifier')
            ->with('hotel-example')
            ->willReturn($this->createMock(Configuration::class));

        $subject = new SiteIdentifierResolver(
            $this->createMock(SiteFinder::class),
            $configurationRepository
        );

        self::assertTrue($subject->hasCasablancaConfiguration('hotel-example'));
    }
}
