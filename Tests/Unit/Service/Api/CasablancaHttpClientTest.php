<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\Api;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Api\CasablancaHttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use RuntimeException;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Http\RequestFactory;

final class CasablancaHttpClientTest extends TestCase
{
    public function testBuildAuthHeadersIncludesApiKeyOnly(): void
    {
        $client = $this->createHttpClient('tenant-uuid-123', 'secret-api-key');

        $method = new ReflectionMethod(CasablancaHttpClient::class, 'buildAuthHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client);

        self::assertSame(['api-key' => 'secret-api-key'], $headers);
        self::assertArrayNotHasKey('x-tenant-id', $headers);
        self::assertArrayNotHasKey('Authorization', $headers);
    }

    public function testBuildAuthHeadersStripsBearerPrefixFromApiKey(): void
    {
        $client = $this->createHttpClient('tenant-uuid-123', 'Bearer secret-api-key');

        $method = new ReflectionMethod(CasablancaHttpClient::class, 'buildAuthHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client);

        self::assertSame('secret-api-key', $headers['api-key']);
        self::assertArrayNotHasKey('x-tenant-id', $headers);
        self::assertArrayNotHasKey('Authorization', $headers);
    }

    public function testBuildAuthHeadersThrowsWhenApiKeyMissing(): void
    {
        $client = $this->createHttpClient('tenant-uuid-123', '');

        $method = new ReflectionMethod(CasablancaHttpClient::class, 'buildAuthHeaders');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API key is missing');

        $method->invoke($client);
    }

    private function createHttpClient(string $tenantId, string $apiKey): CasablancaHttpClient
    {
        $config = new SiteConfigurationDto(
            'test-site',
            $tenantId,
            'bookingengine',
            $apiKey,
            'https://api.casablanca.at',
            'https://bookingengine.casablanca.at',
            'de',
            365,
            31,
            100,
            new RoomOccupancyDto(2)
        );

        return new CasablancaHttpClient(
            $this->createMock(RequestFactory::class),
            $this->createMock(GuzzleClientFactory::class),
            new NullLogger(),
            $config
        );
    }
}
