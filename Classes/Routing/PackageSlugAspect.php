<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Routing;

use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use TYPO3\CMS\Core\Routing\Aspect\SiteAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Site\SiteAwareInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Validates package slugs for Extbase route enhancers on package detail pages.
 */
final class PackageSlugAspect implements StaticMappableAspectInterface, SiteAwareInterface, \Countable
{
    use SiteAccessorTrait;

    /** @var array<string, mixed> */
    protected $settings;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function generate(string $value): ?string
    {
        return $this->resolve($value);
    }

    public function resolve(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !isset($this->site)) {
            return null;
        }

        $package = $this->getRepository()->findOneBySiteAndSlug(
            $this->site->getIdentifier(),
            $value
        );

        return $package !== null ? $value : null;
    }

    public function count(): int
    {
        if (!isset($this->site)) {
            return 0;
        }

        return count($this->getRepository()->findPackagesForSite($this->site->getIdentifier()));
    }

    private function getRepository(): RateRepository
    {
        return GeneralUtility::makeInstance(RateRepository::class);
    }
}
