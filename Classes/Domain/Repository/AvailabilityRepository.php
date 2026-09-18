<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Repository;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Model\Availability;
use DateTimeImmutable;
use DateTimeInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Read-path repository for the Availability cache.
 */
final class AvailabilityRepository
{
    public const TABLE = 'tx_casablancabooking_domain_model_availability';

    /** @var ConnectionPool */
    private $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @return Availability[]
     */
    public function findForSiteAndRange(
        string $siteIdentifier,
        DateTimeInterface $from,
        DateTimeInterface $until,
        ?string $roomTypeId = null
    ): array {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $qb->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq(
                    'site_identifier',
                    $qb->createNamedParameter($siteIdentifier)
                ),
                $qb->expr()->gte(
                    'effective_date',
                    $qb->createNamedParameter($from->format('Y-m-d'))
                ),
                $qb->expr()->lte(
                    'effective_date',
                    $qb->createNamedParameter($until->format('Y-m-d'))
                )
            )
            ->orderBy('effective_date', 'ASC')
            ->addOrderBy('from_price', 'ASC');

        if ($roomTypeId !== null && $roomTypeId !== '') {
            $qb->andWhere(
                $qb->expr()->eq(
                    'room_type_id',
                    $qb->createNamedParameter($roomTypeId)
                ),
                $qb->expr()->eq('rate_id', $qb->createNamedParameter(''))
            );
        } else {
            $qb->andWhere(
                $qb->expr()->eq('rate_id', $qb->createNamedParameter(''))
            );
        }

        $rows = Typo3Adapter::fetchAllAssociative(Typo3Adapter::executeQuery($qb));

        if ($roomTypeId !== null && $roomTypeId !== '') {
            $entities = [];
            foreach ($rows as $row) {
                $entities[] = $this->hydrate($row);
            }

            return $entities;
        }

        $bestPerDate = [];
        foreach ($rows as $row) {
            $date = $row['effective_date'];
            if (!isset($bestPerDate[$date])) {
                $bestPerDate[$date] = $row;
                continue;
            }
            $existing = $bestPerDate[$date];
            if ($row['is_available'] && !$existing['is_available']) {
                $bestPerDate[$date] = $row;
                continue;
            }
            if ($row['is_available'] === $existing['is_available']
                && $row['from_price'] !== null
                && ($existing['from_price'] === null
                    || $row['from_price'] < $existing['from_price'])
            ) {
                $bestPerDate[$date] = $row;
            }
        }

        ksort($bestPerDate);
        $entities = [];
        foreach (array_values($bestPerDate) as $row) {
            $entities[] = $this->hydrate($row);
        }

        return $entities;
    }

    /**
     * @return array{price: float, currency: string}|null
     */
    public function findCheapestPrice(
        string $siteIdentifier,
        DateTimeInterface $from,
        DateTimeInterface $until,
        ?string $roomTypeId = null,
        ?string $rateId = null
    ): ?array {
        $result = $this->queryCheapestPrice($siteIdentifier, $from, $until, $roomTypeId, $rateId);
        if ($result !== null) {
            return $result;
        }

        return $this->queryCheapestPrice($siteIdentifier, $from, null, $roomTypeId, $rateId);
    }

    private function queryCheapestPrice(
        string $siteIdentifier,
        DateTimeInterface $from,
        ?DateTimeInterface $until,
        ?string $roomTypeId,
        ?string $rateId
    ): ?array {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $qb->select('from_price', 'currency')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq(
                    'site_identifier',
                    $qb->createNamedParameter($siteIdentifier)
                ),
                $qb->expr()->gte(
                    'effective_date',
                    $qb->createNamedParameter($from->format('Y-m-d'))
                ),
                $qb->expr()->eq('is_available', 1),
                $qb->expr()->gt('from_price', 0)
            );

        if ($until !== null) {
            $qb->andWhere(
                $qb->expr()->lte(
                    'effective_date',
                    $qb->createNamedParameter($until->format('Y-m-d'))
                )
            );
        }

        if ($rateId !== null && $rateId !== '') {
            $qb->andWhere(
                $qb->expr()->eq(
                    'rate_id',
                    $qb->createNamedParameter($rateId)
                )
            );
        } elseif ($roomTypeId !== null && $roomTypeId !== '') {
            $qb->andWhere(
                $qb->expr()->eq(
                    'room_type_id',
                    $qb->createNamedParameter($roomTypeId)
                ),
                $qb->expr()->eq('rate_id', $qb->createNamedParameter(''))
            );
        } else {
            $qb->andWhere(
                $qb->expr()->eq('rate_id', $qb->createNamedParameter(''))
            );
        }

        $result = Typo3Adapter::executeQuery(
            $qb->orderBy('from_price', 'ASC')->setMaxResults(1)
        );
        $rows = Typo3Adapter::fetchAllAssociative($result);
        $row = $rows[0] ?? null;
        if ($row === null || $row['from_price'] === null) {
            return null;
        }

        return [
            'price' => (float)$row['from_price'],
            'currency' => (string)($row['currency'] ?? 'EUR'),
        ];
    }

    /**
     * @return int[]
     */
    public function findBookablePackageNights(
        string $siteIdentifier,
        DateTimeInterface $from,
        DateTimeInterface $until,
        string $rateId
    ): array {
        if ($rateId === '') {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->select('bookable_nights_with_packages')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq(
                    'site_identifier',
                    $qb->createNamedParameter($siteIdentifier)
                ),
                $qb->expr()->eq(
                    'rate_id',
                    $qb->createNamedParameter($rateId)
                ),
                $qb->expr()->gte(
                    'effective_date',
                    $qb->createNamedParameter($from->format('Y-m-d'))
                ),
                $qb->expr()->lte(
                    'effective_date',
                    $qb->createNamedParameter($until->format('Y-m-d'))
                )
            );

        $nights = [];
        foreach (Typo3Adapter::fetchAllAssociative(Typo3Adapter::executeQuery($qb)) as $row) {
            $raw = (string)($row['bookable_nights_with_packages'] ?? '');
            if ($raw === '') {
                continue;
            }
            foreach (array_map('intval', explode(',', $raw)) as $nightCount) {
                $nights[$nightCount] = true;
            }
        }

        return array_keys($nights);
    }

    public function countForSite(string $siteIdentifier): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $value = Typo3Adapter::fetchOne(
            Typo3Adapter::executeQuery(
                $qb->count('*')
                    ->from(self::TABLE)
                    ->where(
                        $qb->expr()->eq(
                            'site_identifier',
                            $qb->createNamedParameter($siteIdentifier)
                        )
                    )
            )
        );

        return (int)$value;
    }

    public function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Availability
    {
        /** @var Availability $entity */
        $entity = GeneralUtility::makeInstance(Availability::class);

        $bind = function (array $r): void {
            /** @var Availability $this */
            $this->siteIdentifier = (string)($r['site_identifier'] ?? '');
            $this->tenantId = (string)($r['tenant_id'] ?? '');
            $this->ibeContextId = (string)($r['ibe_context_id'] ?? '');
            $this->roomTypeId = (string)($r['room_type_id'] ?? '');
            $this->effectiveDate = $r['effective_date'] !== null && $r['effective_date'] !== ''
                ? new DateTimeImmutable((string)$r['effective_date'])
                : null;
            $this->fromPrice = $r['from_price'] !== null
                ? (float)$r['from_price']
                : null;
            $this->currency = (string)($r['currency'] ?? 'EUR');
            $this->isAvailable = (bool)($r['is_available'] ?? false);
            $this->isArrivalAllowed = (bool)($r['is_arrival_allowed'] ?? false);
            $this->isDepartureAllowed = (bool)($r['is_departure_allowed'] ?? false);
            $this->minLengthOfStay = (int)($r['min_length_of_stay'] ?? 0);
            $this->maxLengthOfStay = (int)($r['max_length_of_stay'] ?? 0);
            $this->bookableNights = $r['bookable_nights']
                ? array_map('intval', explode(',', (string)$r['bookable_nights']))
                : [];
            $this->bookableNightsWithPackages = $r['bookable_nights_with_packages']
                ? array_map('intval', explode(',', (string)$r['bookable_nights_with_packages']))
                : [];
            $this->restrictions = $r['restrictions']
                ? array_filter(array_map('trim', explode(',', (string)$r['restrictions'])))
                : [];
            $this->previousDayBlocked = (bool)($r['previous_day_blocked'] ?? false);
            $this->nextDayBlocked = (bool)($r['next_day_blocked'] ?? false);
        };

        $bind->call($entity, $row);

        return $entity;
    }
}
