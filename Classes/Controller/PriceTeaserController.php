<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Domain\Repository\AvailabilityRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use Casablanca\CasablancaBooking\Service\Api\ApiClientFactory;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Compact "from €X" price teaser with JSON-LD for SEO landing pages.
 */
class PriceTeaserController extends AbstractWidgetController
{
    /** @var AvailabilityRepository */
    private $availabilityRepository;

    /** @var RoomTypeRepository */
    private $roomTypeRepository;

    public function __construct(
        SiteFinder $siteFinder,
        ApiClientFactory $apiClientFactory,
        IbeUrlBuilder $ibeUrlBuilder,
        AvailabilityRepository $availabilityRepository,
        RoomTypeRepository $roomTypeRepository
    ) {
        parent::__construct($siteFinder, $apiClientFactory, $ibeUrlBuilder);
        $this->availabilityRepository = $availabilityRepository;
        $this->roomTypeRepository = $roomTypeRepository;
    }

    /**
     * @return mixed
     */
    public function showAction()
    {
        $this->includeFrontendAssets();

        $config = $this->resolveConfig();
        if ($config === null) {
            $this->view->assign('error', 'not_configured');

            return $this->htmlResponseOrNull();
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $common = $this->parseCommonSettings($config);
        $roomTypeId = trim((string)($settings['roomType'] ?? ''));

        $from = new DateTimeImmutable('today');
        $until = $from->modify(sprintf('+%d days', $common['windowDays'] - 1));

        $cheapest = $this->availabilityRepository->findCheapestPrice(
            $config->siteIdentifier,
            $from,
            $until,
            $roomTypeId !== '' ? $roomTypeId : null
        );

        $roomName = null;
        if ($roomTypeId !== '') {
            $roomType = $this->roomTypeRepository->findOneBySiteAndRoomTypeId(
                $config->siteIdentifier,
                $roomTypeId
            );
            if ($roomType !== null) {
                $roomName = $roomType->getName();
            }
        }

        $availability = $this->availabilityRepository->findForSiteAndRange(
            $config->siteIdentifier,
            $from,
            $until,
            $roomTypeId !== '' ? $roomTypeId : null
        );

        $this->tagPageCache($config, $roomTypeId);

        $this->view->assignMultiple([
            'config' => $config,
            'fromPrice' => $cheapest !== null ? ($cheapest['price'] ?? null) : null,
            'currency' => $cheapest !== null ? ($cheapest['currency'] ?? 'EUR') : 'EUR',
            'roomTypeId' => $roomTypeId,
            'roomName' => $roomName,
            'windowDays' => $common['windowDays'],
            'from' => $from,
            'until' => $until,
            'jsonLd' => $this->buildJsonLd($config, $availability, $roomName),
        ]);

        return $this->htmlResponseOrNull();
    }
}
