<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Repository;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Model\RoomType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Read repository for the RoomType mirror table.
 */
final class RoomTypeRepository
{
    public const TABLE = 'tx_casablancabooking_domain_model_roomtype';

    /** @var ConnectionPool */
    private $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * @return RoomType[]
     */
    public function findForSite(string $siteIdentifier): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = Typo3Adapter::fetchAllAssociative(
            Typo3Adapter::executeQuery(
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
            )
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }

        return $result;
    }

    public function findOneBySiteAndRoomTypeId(
        string $siteIdentifier,
        string $roomTypeId
    ): ?RoomType {
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
                            'room_type_id',
                            $qb->createNamedParameter($roomTypeId)
                        )
                    )
                    ->setMaxResults(1)
            )
        );

        if ($rows === []) {
            return null;
        }

        return $this->hydrate($rows[0]);
    }

    public function findOneBySiteAndSlug(string $siteIdentifier, string $slug): ?RoomType
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
                        )
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
                )
            );

        if ($excludeUid > 0) {
            $query->andWhere(
                $qb->expr()->neq(
                    'uid',
                    $qb->createNamedParameter($excludeUid, Connection::PARAM_INT)
                )
            );
        }

        $rows = Typo3Adapter::fetchAllAssociative(
            Typo3Adapter::executeQuery($query->setMaxResults(1))
        );

        return $rows !== [];
    }

    public function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): RoomType
    {
        /** @var RoomType $entity */
        $entity = GeneralUtility::makeInstance(RoomType::class);

        $images = [];
        if (!empty($row['images'])) {
            $decoded = json_decode((string)$row['images'], true);
            if (is_array($decoded)) {
                $images = $decoded;
            }
        }

        $bind = function (array $r) use ($images): void {
            /** @var RoomType $this */
            $this->_setProperty('uid', (int)($r['uid'] ?? 0));
            $this->_setProperty('pid', (int)($r['pid'] ?? 0));
            $this->siteIdentifier = (string)($r['site_identifier'] ?? '');
            $this->tenantId = (string)($r['tenant_id'] ?? '');
            $this->ibeContextId = (string)($r['ibe_context_id'] ?? '');
            $this->roomTypeId = (string)($r['room_type_id'] ?? '');
            $this->companyId = (string)($r['company_id'] ?? '');
            $this->name = (string)($r['name'] ?? '');
            $this->slug = (string)($r['slug'] ?? '');
            $this->description = (string)($r['description'] ?? '');
            $this->imageUrl = (string)($r['image_url'] ?? '');
            $this->images = $images;
            $this->standardOccupancy = (int)($r['standard_occupancy'] ?? 2);
            $this->minOccupancy = (int)($r['min_occupancy'] ?? 1);
            $this->maxOccupancy = (int)($r['max_occupancy'] ?? 4);
            $this->sortOrder = (int)($r['sort_order'] ?? 0);
        };

        $bind->call($entity, $row);

        return $entity;
    }
}
