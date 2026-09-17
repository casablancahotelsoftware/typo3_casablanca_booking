<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Backend;

use Casablanca\CasablancaBooking\Domain\Repository\ConfigurationRepository;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Resolves the TYPO3 site identifier from FormEngine itemsProcFunc parameters.
 */
final class SiteIdentifierResolver
{
    /** @var SiteFinder */
    private $siteFinder;

    /** @var ConfigurationRepository */
    private $configurationRepository;

    public function __construct(
        SiteFinder $siteFinder,
        ConfigurationRepository $configurationRepository
    ) {
        $this->siteFinder = $siteFinder;
        $this->configurationRepository = $configurationRepository;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function resolveFromItemsProcParams(array $params): ?string
    {
        $site = $params['site'] ?? null;
        if ($site instanceof Site && !($site instanceof NullSite)) {
            $identifier = $site->getIdentifier();
            if ($identifier !== '') {
                return $identifier;
            }
        }

        $pageIds = [
            (int)($params['effectivePid'] ?? 0),
            (int)($params['flexParentDatabaseRow']['pid'] ?? 0),
            (int)($params['row']['pid'] ?? 0),
        ];

        foreach ($pageIds as $pageId) {
            $identifier = $this->resolveByPageId($pageId);
            if ($identifier !== null) {
                return $identifier;
            }
        }

        return $this->resolveSingleConfiguredSiteFallback();
    }

    public function hasCasablancaConfiguration(string $siteIdentifier): bool
    {
        if ($siteIdentifier === '') {
            return false;
        }

        if ($this->configurationRepository->findBySiteIdentifier($siteIdentifier) !== null) {
            return true;
        }

        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            $yamlSettings = (array)($site->getConfiguration()['casablanca_booking'] ?? []);

            return $yamlSettings !== [];
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function resolveByPageId(int $pageId): ?string
    {
        if ($pageId <= 0) {
            return null;
        }

        try {
            return $this->siteFinder->getSiteByPageId($pageId)->getIdentifier();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolveSingleConfiguredSiteFallback(): ?string
    {
        $configurations = $this->configurationRepository->findAll();
        if (count($configurations) !== 1) {
            return null;
        }

        $siteIdentifier = $configurations[0]->getSiteIdentifier();

        return $siteIdentifier !== '' ? $siteIdentifier : null;
    }
}
