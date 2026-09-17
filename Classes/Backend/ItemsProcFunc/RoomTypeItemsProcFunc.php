<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Backend\ItemsProcFunc;

use Casablanca\CasablancaBooking\Backend\SiteIdentifierResolver;
use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;

/**
 * Populates FlexForm room-category dropdowns from the synced room-type mirror.
 */
class RoomTypeItemsProcFunc
{
    /** @var RoomTypeRepository */
    private $roomTypeRepository;

    /** @var SiteIdentifierResolver */
    private $siteIdentifierResolver;

    public function __construct(
        RoomTypeRepository $roomTypeRepository,
        SiteIdentifierResolver $siteIdentifierResolver
    ) {
        $this->roomTypeRepository = $roomTypeRepository;
        $this->siteIdentifierResolver = $siteIdentifierResolver;
    }

    /**
     * @param array{items: array<int, array{0: string, 1: string}>, row: array<string, mixed>} $params
     */
    public function getRoomTypes(array &$params): void
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

        foreach ($this->roomTypeRepository->findForSite($siteIdentifier) as $roomType) {
            $params['items'][] = [
                sprintf('%s (%s)', $roomType->getName(), $roomType->getRoomTypeId()),
                $roomType->getRoomTypeId(),
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
