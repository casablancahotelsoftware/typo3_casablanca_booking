<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\BookingOffer;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Model\Rate;
use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use Casablanca\CasablancaBooking\Service\Api\Client\BookingOffersClient;

/**
 * Fetches and normalises booking offers for the availability calendar sidebar.
 */
final class BookingOffersFetchService
{
    public const MODE_NONE = 'none';
    public const MODE_RATES_ONLY = 'rates_only';
    public const MODE_PACKAGES_ONLY = 'packages_only';
    public const MODE_PACKAGES_AND_RATES = 'packages_and_rates';

    /** @var RateRepository */
    private $rateRepository;

    public function __construct(RateRepository $rateRepository)
    {
        $this->rateRepository = $rateRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(
        BookingOffersClient $client,
        SiteConfigurationDto $config,
        string $arrivalDate,
        string $departureDate,
        RoomOccupancyDto $roomOccupancy,
        string $offerMode,
        ?string $preselectedRoomTypeId = null
    ): array {
        $offerMode = self::normaliseOfferMode($offerMode);
        if ($offerMode === self::MODE_NONE) {
            return [
                'offerMode' => self::MODE_NONE,
                'offers' => [],
                'lowestTotalPrice' => null,
                'currency' => 'EUR',
            ];
        }

        $selectionCriteria = $this->buildSelectionCriteria($offerMode);
        $stayFilter = null;
        if ($preselectedRoomTypeId !== null && $preselectedRoomTypeId !== '') {
            $stayFilter = [
                'companyIdentifiers' => [],
                'roomTypeIds' => [$preselectedRoomTypeId],
                'rateIds' => [],
            ];
        }

        $response = $client->create(
            $arrivalDate,
            $departureDate,
            $roomOccupancy,
            $selectionCriteria,
            $stayFilter ?? []
        );

        $offers = $this->parseOffers($config, $offerMode, $response);
        $lowest = null;
        $currency = 'EUR';

        foreach ($offers as $offer) {
            $price = $offer['totalPrice'] ?? null;
            if ($price === null) {
                continue;
            }
            if ($lowest === null || $price < $lowest) {
                $lowest = $price;
            }
            if (($offer['currency'] ?? '') !== '') {
                $currency = (string)$offer['currency'];
            }
        }

        return [
            'offerMode' => $offerMode,
            'offers' => $offers,
            'lowestTotalPrice' => $lowest,
            'currency' => $currency,
        ];
    }

    public static function normaliseOfferMode(string $offerMode): string
    {
        $allowed = [
            self::MODE_NONE,
            self::MODE_RATES_ONLY,
            self::MODE_PACKAGES_ONLY,
            self::MODE_PACKAGES_AND_RATES,
        ];

        return in_array($offerMode, $allowed, true) ? $offerMode : self::MODE_NONE;
    }

    /**
     * @return string[]
     */
    private function buildSelectionCriteria(string $offerMode): array
    {
        switch ($offerMode) {
            case self::MODE_RATES_ONLY:
                return ['includeRates'];
            case self::MODE_PACKAGES_ONLY:
                return ['includePackages'];
            case self::MODE_PACKAGES_AND_RATES:
                return ['includePackages', 'includeRates'];
            default:
                return [];
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function parseOffers(
        SiteConfigurationDto $config,
        string $offerMode,
        array $response
    ): array {
        $configurations = isset($response['configurations']) && is_array($response['configurations'])
            ? $response['configurations']
            : [];
        if ($configurations === []) {
            return [];
        }

        $configuration = $configurations[0];
        if (!is_array($configuration)) {
            return [];
        }

        $offers = [];

        if ($offerMode === self::MODE_PACKAGES_ONLY || $offerMode === self::MODE_PACKAGES_AND_RATES) {
            $packageGroups = isset($configuration['packageGroups']) && is_array($configuration['packageGroups'])
                ? $configuration['packageGroups']
                : [];
            foreach ($packageGroups as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $offer = $this->cheapestOfferFromGroup($group, 'packages');
                if ($offer === null) {
                    continue;
                }
                $offers[] = $this->enrichOffer($config, $offer, (string)($group['packageId'] ?? ''), true);
            }
        }

        if ($offerMode === self::MODE_RATES_ONLY || $offerMode === self::MODE_PACKAGES_AND_RATES) {
            $rateGroups = isset($configuration['rateGroups']) && is_array($configuration['rateGroups'])
                ? $configuration['rateGroups']
                : [];
            $seenRateIds = [];
            foreach ($rateGroups as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $roomStayOffers = isset($group['roomStayOffers']) && is_array($group['roomStayOffers'])
                    ? $group['roomStayOffers']
                    : [];
                foreach ($roomStayOffers as $roomStayOffer) {
                    if (!is_array($roomStayOffer)) {
                        continue;
                    }
                    $rateId = (string)($roomStayOffer['rateId'] ?? '');
                    if ($rateId === '' || isset($seenRateIds[$rateId])) {
                        continue;
                    }
                    $totalPrice = $this->parsePrice($roomStayOffer['totalPrice'] ?? null);
                    if ($totalPrice === null) {
                        continue;
                    }
                    $existing = $seenRateIds[$rateId] ?? null;
                    if ($existing !== null && $existing['totalPrice'] <= $totalPrice) {
                        continue;
                    }
                    $candidate = [
                        'rateId' => $rateId,
                        'totalPrice' => $totalPrice,
                        'currency' => 'EUR',
                        'section' => 'rates',
                        'isPackage' => false,
                    ];
                    $seenRateIds[$rateId] = $candidate;
                }
            }
            foreach ($seenRateIds as $candidate) {
                $offers[] = $this->enrichOffer(
                    $config,
                    $candidate,
                    (string)$candidate['rateId'],
                    false
                );
            }
        }

        usort($offers, static function (array $a, array $b): int {
            $priceA = $a['totalPrice'] ?? PHP_FLOAT_MAX;
            $priceB = $b['totalPrice'] ?? PHP_FLOAT_MAX;
            if ($priceA === $priceB) {
                return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
            }

            return $priceA <=> $priceB;
        });

        if ($offerMode === self::MODE_PACKAGES_AND_RATES) {
            usort($offers, static function (array $a, array $b): int {
                $sectionOrder = ['packages' => 0, 'rates' => 1];
                $orderA = $sectionOrder[$a['section'] ?? 'rates'] ?? 1;
                $orderB = $sectionOrder[$b['section'] ?? 'rates'] ?? 1;
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }
                $priceA = $a['totalPrice'] ?? PHP_FLOAT_MAX;
                $priceB = $b['totalPrice'] ?? PHP_FLOAT_MAX;
                if ($priceA === $priceB) {
                    return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                }

                return $priceA <=> $priceB;
            });
        }

        return $offers;
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>|null
     */
    private function cheapestOfferFromGroup(array $group, string $section): ?array
    {
        $roomStayOffers = isset($group['roomStayOffers']) && is_array($group['roomStayOffers'])
            ? $group['roomStayOffers']
            : [];
        $cheapest = null;
        $rateId = '';

        foreach ($roomStayOffers as $roomStayOffer) {
            if (!is_array($roomStayOffer)) {
                continue;
            }
            $totalPrice = $this->parsePrice($roomStayOffer['totalPrice'] ?? null);
            if ($totalPrice === null) {
                continue;
            }
            if ($cheapest === null || $totalPrice < $cheapest) {
                $cheapest = $totalPrice;
                $rateId = (string)($roomStayOffer['rateId'] ?? $group['packageId'] ?? '');
            }
        }

        if ($cheapest === null) {
            return null;
        }

        $packageId = (string)($group['packageId'] ?? $rateId);

        return [
            'rateId' => $rateId !== '' ? $rateId : $packageId,
            'totalPrice' => $cheapest,
            'currency' => 'EUR',
            'section' => $section,
            'isPackage' => true,
        ];
    }

    /**
     * @param array<string, mixed> $offer
     * @return array<string, mixed>
     */
    private function enrichOffer(
        SiteConfigurationDto $config,
        array $offer,
        string $lookupId,
        bool $isPackage
    ): array {
        $rateId = (string)($offer['rateId'] ?? $lookupId);
        $rate = $this->rateRepository->findOneBySiteAndRateId($config->siteIdentifier, $rateId);
        if ($rate === null && $lookupId !== '' && $lookupId !== $rateId) {
            $rate = $this->rateRepository->findOneBySiteAndRateId($config->siteIdentifier, $lookupId);
        }

        $name = $rateId;
        $subtitle = '';
        $detailUrl = '';

        if ($rate instanceof Rate) {
            $name = $rate->getName() !== '' ? $rate->getName() : $rateId;
            $subtitle = $rate->getFormattedCateringType();
            $isPackage = $rate->isPackage();
        }

        return [
            'rateId' => $rateId,
            'name' => $name,
            'subtitle' => $subtitle,
            'totalPrice' => $offer['totalPrice'] ?? null,
            'currency' => $offer['currency'] ?? 'EUR',
            'isPackage' => $isPackage,
            'section' => $offer['section'] ?? ($isPackage ? 'packages' : 'rates'),
            'detailUrl' => $detailUrl,
        ];
    }

    /**
     * @param mixed $value
     */
    private function parsePrice($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }
}
