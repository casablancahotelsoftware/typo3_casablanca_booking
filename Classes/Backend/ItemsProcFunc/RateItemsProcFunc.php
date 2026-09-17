<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Backend\ItemsProcFunc;

use Casablanca\CasablancaBooking\Backend\SiteIdentifierResolver;
use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;

/**
 * Populates FlexForm package/rate dropdowns from the synced rates mirror.
 */
class RateItemsProcFunc
{
    /** @var RateRepository */
    private $rateRepository;

    /** @var SiteIdentifierResolver */
    private $siteIdentifierResolver;

    public function __construct(
        RateRepository $rateRepository,
        SiteIdentifierResolver $siteIdentifierResolver
    ) {
        $this->rateRepository = $rateRepository;
        $this->siteIdentifierResolver = $siteIdentifierResolver;
    }

    /**
     * @param array{items: array<int, array{0: string, 1: string}>, row: array<string, mixed>} $params
     */
    public function getPackages(array &$params): void
    {
        $siteIdentifier = $this->siteIdentifierResolver->resolveFromItemsProcParams($params);
        if ($siteIdentifier === null) {
            $params['items'][] = [
                'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:flexform.preselect.no_site',
                '',
            ];

            return;
        }

        if (!$this->siteIdentifierResolver->hasCasablancaConfiguration($siteIdentifier)) {
            $params['items'][] = [
                'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:flexform.preselect.no_site_config',
                '',
            ];

            return;
        }

        foreach ($this->rateRepository->findPackagesForSite($siteIdentifier) as $rate) {
            $params['items'][] = [
                sprintf('%s (%s)', $rate->getName(), $rate->getRateId()),
                $rate->getRateId(),
            ];
        }

        if (count($params['items']) <= 1) {
            $params['items'][] = [
                'LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_db.xlf:flexform.preselect.none_synced',
                '',
            ];
        }
    }
}
