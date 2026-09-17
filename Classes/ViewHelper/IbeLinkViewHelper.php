<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\ViewHelper;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use Casablanca\CasablancaBooking\Service\Api\ApiClientFactory;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Builds an IBE deep-link for use in agency Fluid templates.
 *
 * Usage: {casablanca:ibeLink(siteIdentifier: 'main', arrival: '2026-06-01', departure: '2026-06-08', adults: 2)}
 */
class IbeLinkViewHelper extends AbstractViewHelper
{
    /** @var bool */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('siteIdentifier', 'string', 'TYPO3 site identifier', true);
        $this->registerArgument('arrival', 'string', 'Arrival date (Y-m-d)', false, '');
        $this->registerArgument('departure', 'string', 'Departure date (Y-m-d)', false, '');
        $this->registerArgument('adults', 'int', 'Number of adults', false, 2);
        $this->registerArgument('children', 'int', 'Number of children', false, 0);
        $this->registerArgument('childrenAges', 'string', 'Comma-separated child ages', false, '');
        $this->registerArgument('roomTypeIds', 'string', 'Pre-selected room type ID(s), comma-separated', false, '');
        $this->registerArgument('rateIds', 'string', 'Optional rate ID to preselect a package', false, '');
        $this->registerArgument('culture', 'string', 'IBE culture segment', false, '');
        $this->registerArgument(
            'linkStyle',
            'string',
            'Override IBE link style (full_path, tenant_only, culture_only)',
            false,
            ''
        );
    }

    public function render(): string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $apiClientFactory = GeneralUtility::makeInstance(ApiClientFactory::class);
        $ibeUrlBuilder = GeneralUtility::makeInstance(IbeUrlBuilder::class);

        $site = $siteFinder->getSiteByIdentifier((string)$this->arguments['siteIdentifier']);
        $config = $apiClientFactory->resolveConfiguration($site);
        if ($config === null) {
            return '';
        }

        $arrival = trim((string)$this->arguments['arrival']);
        $departure = trim((string)$this->arguments['departure']);
        $arrivalDate = new DateTimeImmutable($arrival !== '' ? $arrival : 'tomorrow');
        $departureDate = new DateTimeImmutable($departure !== '' ? $departure : 'tomorrow +1 day');

        $ages = [];
        $agesRaw = trim((string)$this->arguments['childrenAges']);
        if ($agesRaw !== '') {
            $ages = array_map('intval', explode(',', $agesRaw));
        }
        $childCount = max(0, (int)$this->arguments['children']);
        while (count($ages) < $childCount) {
            $ages[] = 0;
        }
        if (count($ages) > $childCount) {
            $ages = array_slice($ages, 0, $childCount);
        }

        $occupancy = new RoomOccupancyDto(max(1, (int)$this->arguments['adults']), $ages);

        $roomTypeIds = trim((string)$this->arguments['roomTypeIds']);
        $rateIds = trim((string)$this->arguments['rateIds']);
        $culture = trim((string)$this->arguments['culture']);
        $linkStyleOverride = trim((string)$this->arguments['linkStyle']);
        if ($linkStyleOverride !== '') {
            $config->ibeLinkStyle = IbeLinkStyle::normalize($linkStyleOverride);
        }

        return $ibeUrlBuilder->build(
            $config,
            $arrivalDate,
            $departureDate,
            $occupancy,
            $roomTypeIds !== '' ? $roomTypeIds : null,
            $culture !== '' ? $culture : null,
            $rateIds !== '' ? $rateIds : null
        );
    }
}
