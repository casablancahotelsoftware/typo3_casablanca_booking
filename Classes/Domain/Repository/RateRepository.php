<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Repository;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Model\Rate;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Read repository for the Rate mirror table.
 */
final class RateRepository
{
    public const TABLE = 'tx_casablancabooking_domain_model_rate';

    /** @var ConnectionPool */
    private $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @return Rate[]
     */
    public function findForSite(string $siteIdentifier): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $result = Typo3Adapter::executeQuery(
            $qb->select('*')
                ->from(self::TABLE)
                ->where(
                    $qb->expr()->eq(
                        'site_identifier',
                        $qb->createNamedParameter($siteIdentifier)
                    )
                )
                ->orderBy('sort_order', 'ASC')
                ->addOrderBy('name', 'ASC')
        );

        $rows = Typo3Adapter::fetchAllAssociative($result);
        $rates = [];
        foreach ($rows as $row) {
            $rates[] = $this->hydrate($row);
        }

        return $rates;
    }

    /**
     * @return Rate[]
     */
    public function findPackagesForSite(string $siteIdentifier): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $result = Typo3Adapter::executeQuery(
            $qb->select('*')
                ->from(self::TABLE)
                ->where(
                    $qb->expr()->eq(
                        'site_identifier',
                        $qb->createNamedParameter($siteIdentifier)
                    ),
                    $qb->expr()->eq('is_package', 1)
                )
                ->orderBy('sort_order', 'ASC')
                ->addOrderBy('name', 'ASC')
        );

        $rows = Typo3Adapter::fetchAllAssociative($result);
        $rates = [];
        foreach ($rows as $row) {
            $rates[] = $this->hydrate($row);
        }

        return $rates;
    }

    public function findOneBySiteAndSlug(string $siteIdentifier, string $slug): ?Rate
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = Typo3Adapter::fetchAllAssociative(
            Typo3Adapter::executeQuery(
                $qb->select('*')
                    ->from(self::TABLE)
                    ->where(
                        $qb->expr()->eq(
                            'site_identifier',
                            $qb->createNamedParameter($siteIdentifier)
                        ),
                        $qb->expr()->eq(
                            'slug',
                            $qb->createNamedParameter($slug)
                        ),
                        $qb->expr()->eq('is_package', 1)
                    )
                    ->setMaxResults(1)
            )
        );

        if ($rows === []) {
            return null;
        }

        return $this->hydrate($rows[0]);
    }

    public function slugExistsForSite(string $siteIdentifier, string $slug, int $excludeUid = 0): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $query = $qb->select('uid')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq(
                    'site_identifier',
                    $qb->createNamedParameter($siteIdentifier)
                ),
                $qb->expr()->eq(
                    'slug',
                    $qb->createNamedParameter($slug)
                ),
                $qb->expr()->eq('is_package', 1)
            );

        if ($excludeUid > 0) {
            $query->andWhere(
                $qb->expr()->neq(
                    'uid',
                    $qb->createNamedParameter($excludeUid, Connection::PARAM_INT)
                )
            );
        }

        $rows = Typo3Adapter::fetchAllAssociative(Typo3Adapter::executeQuery($query));

        return $rows !== [];
    }

    public function findOneBySiteAndRateId(string $siteIdentifier, string $rateId): ?Rate
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $result = Typo3Adapter::executeQuery(
            $qb->select('*')
                ->from(self::TABLE)
                ->where(
                    $qb->expr()->eq(
                        'site_identifier',
                        $qb->createNamedParameter($siteIdentifier)
                    ),
                    $qb->expr()->eq(
                        'rate_id',
                        $qb->createNamedParameter($rateId)
                    )
                )
                ->setMaxResults(1)
        );

        $rows = Typo3Adapter::fetchAllAssociative($result);
        if ($rows === []) {
            return null;
        }

        return $this->hydrate($rows[0]);
    }

    public function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Rate
    {
        /** @var Rate $entity */
        $entity = GeneralUtility::makeInstance(Rate::class);

        $bind = function (array $r): void {
            /** @var Rate $this */
            $this->_setProperty('uid', (int)($r['uid'] ?? 0));
            $this->_setProperty('pid', (int)($r['pid'] ?? 0));
            $this->siteIdentifier = (string)($r['site_identifier'] ?? '');
            $this->tenantId = (string)($r['tenant_id'] ?? '');
            $this->ibeContextId = (string)($r['ibe_context_id'] ?? '');
            $this->rateId = (string)($r['rate_id'] ?? '');
            $this->name = (string)($r['name'] ?? '');
            $this->slug = (string)($r['slug'] ?? '');
            $this->description = (string)($r['description'] ?? '');
            $this->imageUrl = (string)($r['image_url'] ?? '');
            $this->isPackage = (bool)($r['is_package'] ?? false);
            $this->cateringType = (string)($r['catering_type'] ?? '');
            $this->sortOrder = (int)($r['sort_order'] ?? 0);
        };

        $bind->call($entity, $row);

        return $entity;
    }
}
