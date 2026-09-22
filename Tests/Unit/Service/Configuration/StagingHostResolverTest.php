<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\Configuration;

use Casablanca\CasablancaBooking\Service\Configuration\StagingHostResolver;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class StagingHostResolverTest extends TestCase
{
    public function testProductionUrlsUnchangedInProductionMode(): void
    {
        $resolver = new StagingHostResolver($this->createExtensionConfiguration([
            'environmentMode' => StagingHostResolver::MODE_PRODUCTION,
        ]));

        self::assertSame(StagingHostResolver::MODE_PRODUCTION, $resolver->getEnvironmentMode());
        self::assertFalse($resolver->isAlternateEnvironmentEnabled());
        self::assertSame(
            'https://api.casablanca.at',
            $resolver->applyToApiUrl('https://api.casablanca.at')
        );
        self::assertSame(
            'https://bookingengine.casablanca.at/de/demo',
            $resolver->applyToIbeUrl('https://bookingengine.casablanca.at/de/demo')
        );
    }

    public function testProductionHostsAreRewrittenInStagingMode(): void
    {
        $resolver = new StagingHostResolver($this->createExtensionConfiguration([
            'environmentMode' => StagingHostResolver::MODE_STAGING,
        ]));

        self::assertTrue($resolver->isAlternateEnvironmentEnabled());
        self::assertSame(
            'https://staging-api.casablanca.at',
            $resolver->applyToApiUrl('https://api.casablanca.at')
        );
        self::assertSame(
            'https://staging-bookingengine.casablanca.at/de/demo',
            $resolver->applyToIbeUrl('https://bookingengine.casablanca.at/de/demo')
        );
    }

    public function testProductionHostsAreRewrittenInDevelopmentMode(): void
    {
        $resolver = new StagingHostResolver($this->createExtensionConfiguration([
            'environmentMode' => StagingHostResolver::MODE_DEVELOPMENT,
        ]));

        self::assertSame(StagingHostResolver::MODE_DEVELOPMENT, $resolver->getEnvironmentMode());
        self::assertSame(
            'https://dev-api.casablanca.at',
            $resolver->applyToApiUrl('https://api.casablanca.at')
        );
        self::assertSame(
            'https://dev-bookingengine.casablanca.at/de/demo',
            $resolver->applyToIbeUrl('https://bookingengine.casablanca.at/de/demo')
        );
    }

    public function testCustomHostsAreNotRewrittenInAlternateModes(): void
    {
        $resolver = new StagingHostResolver($this->createExtensionConfiguration([
            'environmentMode' => StagingHostResolver::MODE_STAGING,
        ]));

        self::assertSame(
            'https://book.example.com/path',
            $resolver->applyToIbeUrl('https://book.example.com/path')
        );

        $devResolver = new StagingHostResolver($this->createExtensionConfiguration([
            'environmentMode' => StagingHostResolver::MODE_DEVELOPMENT,
        ]));

        self::assertSame(
            'https://book.example.com/path',
            $devResolver->applyToIbeUrl('https://book.example.com/path')
        );
    }

    public function testApplyToSettingsRewritesKnownKeysInStagingMode(): void
    {
        $resolver = new StagingHostResolver($this->createExtensionConfiguration([
            'environmentMode' => StagingHostResolver::MODE_STAGING,
        ]));

        $settings = $resolver->applyToSettings([
            'apiBaseUrl' => 'https://api.casablanca.at',
            'ibeBaseUrl' => 'https://bookingengine.casablanca.at',
        ]);

        self::assertSame('https://staging-api.casablanca.at', $settings['apiBaseUrl']);
        self::assertSame('https://staging-bookingengine.casablanca.at', $settings['ibeBaseUrl']);
    }

    public function testLegacyStagingBooleanFallsBackToStagingMode(): void
    {
        $resolver = new StagingHostResolver($this->createExtensionConfiguration([
            'staging' => '1',
        ]));

        self::assertSame(StagingHostResolver::MODE_STAGING, $resolver->getEnvironmentMode());
        self::assertSame(
            'https://staging-api.casablanca.at',
            $resolver->applyToApiUrl('https://api.casablanca.at')
        );
    }

    /**
     * @param array<string, string> $config
     */
    private function createExtensionConfiguration(array $config): ExtensionConfiguration
    {
        $mock = $this->createMock(ExtensionConfiguration::class);
        $mock->method('get')->with('casablanca_booking')->willReturn($config);

        return $mock;
    }
}
