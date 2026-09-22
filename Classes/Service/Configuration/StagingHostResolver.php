<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Rewrites default CASABLANCA production API and booking-engine hosts when
 * extension configuration selects staging or development mode for this instance.
 */
final class StagingHostResolver
{
    public const MODE_PRODUCTION = 'production';
    public const MODE_STAGING = 'staging';
    public const MODE_DEVELOPMENT = 'development';

    public const PRODUCTION_API_HOST = 'api.casablanca.at';
    public const STAGING_API_HOST = 'staging-api.casablanca.at';
    public const DEV_API_HOST = 'dev-api.casablanca.at';
    public const PRODUCTION_IBE_HOST = 'bookingengine.casablanca.at';
    public const STAGING_IBE_HOST = 'staging-bookingengine.casablanca.at';
    public const DEV_IBE_HOST = 'dev-bookingengine.casablanca.at';

    public function __construct(
        private readonly ?ExtensionConfiguration $extensionConfiguration = null,
    ) {}

    public function getEnvironmentMode(): string
    {
        try {
            $config = $this->getExtensionConfiguration()->get('casablanca_booking');
        } catch (\Throwable) {
            return self::MODE_PRODUCTION;
        }

        if (!is_array($config)) {
            return self::MODE_PRODUCTION;
        }

        $mode = (string)($config['environmentMode'] ?? '');
        if ($mode === self::MODE_STAGING || $mode === self::MODE_DEVELOPMENT) {
            return $mode;
        }

        if (!empty($config['staging'])) {
            return self::MODE_STAGING;
        }

        return self::MODE_PRODUCTION;
    }

    public function isAlternateEnvironmentEnabled(): bool
    {
        return $this->getEnvironmentMode() !== self::MODE_PRODUCTION;
    }

    public function applyToApiUrl(string $url): string
    {
        $targetHost = $this->resolveApiTargetHost();
        if ($targetHost === null) {
            return $url;
        }

        return $this->replaceHost($url, self::PRODUCTION_API_HOST, $targetHost);
    }

    public function applyToIbeUrl(string $url): string
    {
        $targetHost = $this->resolveIbeTargetHost();
        if ($targetHost === null) {
            return $url;
        }

        return $this->replaceHost($url, self::PRODUCTION_IBE_HOST, $targetHost);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function applyToSettings(array $settings): array
    {
        if (!$this->isAlternateEnvironmentEnabled()) {
            return $settings;
        }

        if (isset($settings['apiBaseUrl']) && is_string($settings['apiBaseUrl'])) {
            $settings['apiBaseUrl'] = $this->applyToApiUrl($settings['apiBaseUrl']);
        }
        if (isset($settings['ibeBaseUrl']) && is_string($settings['ibeBaseUrl'])) {
            $settings['ibeBaseUrl'] = $this->applyToIbeUrl($settings['ibeBaseUrl']);
        }

        return $settings;
    }

    private function resolveApiTargetHost(): ?string
    {
        return match ($this->getEnvironmentMode()) {
            self::MODE_STAGING => self::STAGING_API_HOST,
            self::MODE_DEVELOPMENT => self::DEV_API_HOST,
            default => null,
        };
    }

    private function resolveIbeTargetHost(): ?string
    {
        return match ($this->getEnvironmentMode()) {
            self::MODE_STAGING => self::STAGING_IBE_HOST,
            self::MODE_DEVELOPMENT => self::DEV_IBE_HOST,
            default => null,
        };
    }

    private function replaceHost(string $url, string $fromHost, string $toHost): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host']) || $parts['host'] !== $fromHost) {
            return $url;
        }

        $parts['host'] = $toHost;

        return $this->buildUrl($parts);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string)($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    private function getExtensionConfiguration(): ExtensionConfiguration
    {
        return $this->extensionConfiguration
            ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }
}
