<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Routing;

use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use TYPO3\CMS\Core\Routing\Aspect\SiteAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Site\SiteAwareInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Validates room slugs for Extbase route enhancers on room detail pages.
 */
final class RoomSlugAspect implements StaticMappableAspectInterface, SiteAwareInterface, \Countable
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

        $room = $this->getRepository()->findOneBySiteAndSlug(
            $this->site->getIdentifier(),
            $value
        );

        return $room !== null ? $value : null;
    }

    public function count(): int
    {
        if (!isset($this->site)) {
            return 0;
        }

        return count($this->getRepository()->findForSite($this->site->getIdentifier()));
    }

    private function getRepository(): RoomTypeRepository
    {
        return GeneralUtility::makeInstance(RoomTypeRepository::class);
    }
}
