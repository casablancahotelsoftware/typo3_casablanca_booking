<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Repository;

use Casablanca\CasablancaBooking\Domain\Model\SyncLog;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for the sync audit log. Provides both the write path (open
 * a log entry, close it when done) and a simple read for the backend
 * dashboard.
 */
final class SyncLogRepository
{
    public const TABLE = 'tx_casablancabooking_domain_model_synclog';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Inserts a new "running" log row and returns its uid. The caller
     * must complete() it later.
     */
    public function open(string $siteIdentifier): int
    {
        $connection = $this->getConnection();
        $connection->insert(
            self::TABLE,
            [
                'site_identifier' => $siteIdentifier,
                'started_at' => time(),
                'finished_at' => 0,
                'status' => SyncLog::STATUS_RUNNING,
                'rows_written' => 0,
                'rows_changed' => 0,
                'cache_tags_flushed' => 0,
                'message' => '',
            ],
        );
        return (int)$connection->lastInsertId();
    }

    public function complete(
        int $uid,
        string $status,
        int $rowsWritten,
        int $rowsChanged,
        int $cacheTagsFlushed,
        string $message = '',
    ): void {
        $this->getConnection()->update(
            self::TABLE,
            [
                'finished_at' => time(),
                'status' => $status,
                'rows_written' => $rowsWritten,
                'rows_changed' => $rowsChanged,
                'cache_tags_flushed' => $cacheTagsFlushed,
                'message' => $message,
            ],
            ['uid' => $uid],
        );
    }

    /**
     * @return SyncLog[]
     */
    public function findRecent(string $siteIdentifier, int $limit = 20): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $qb->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq(
                    'site_identifier',
                    $qb->createNamedParameter($siteIdentifier),
                ),
            )
            ->orderBy('started_at', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map([$this, 'hydrate'], $rows);
    }

    private function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): SyncLog
    {
        /** @var SyncLog $entity */
        $entity = GeneralUtility::makeInstance(SyncLog::class);

        $bind = function (array $r): void {
            /** @var SyncLog $this */
            $this->_setProperty('uid', (int)($r['uid'] ?? 0));
            $this->siteIdentifier = (string)($r['site_identifier'] ?? '');
            $this->startedAt = (int)($r['started_at'] ?? 0);
            $this->finishedAt = (int)($r['finished_at'] ?? 0);
            $this->status = (string)($r['status'] ?? '');
            $this->rowsWritten = (int)($r['rows_written'] ?? 0);
            $this->rowsChanged = (int)($r['rows_changed'] ?? 0);
            $this->cacheTagsFlushed = (int)($r['cache_tags_flushed'] ?? 0);
            $this->message = (string)($r['message'] ?? '');
        };

        $bind->call($entity, $row);
        return $entity;
    }
}
