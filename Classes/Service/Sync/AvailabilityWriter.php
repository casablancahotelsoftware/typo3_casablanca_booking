<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Repository\AvailabilityRepository;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Bulk-writer for the availability cache using DBAL via Typo3Adapter.
 */
final class AvailabilityWriter
{
    private const BATCH_SIZE = 500;

    /** @var AvailabilityRepository */
    private $availabilityRepository;

    public function __construct(AvailabilityRepository $availabilityRepository)
    {
        $this->availabilityRepository = $availabilityRepository;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function write(SiteConfigurationDto $config, array $rows): WriterResult
    {
        if ($rows === []) {
            return new WriterResult(0, 0, []);
        }

        $connection = $this->availabilityRepository->getConnection();
        $existingHashes = $this->loadExistingHashes($config, $rows);

        $changedRoomIds = [];
        $rowsChanged = 0;

        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $row) {
                $rateId = (string)($row['rate_id'] ?? '');
                $key = $row['site_identifier'] . '|' . $row['room_type_id']
                    . '|' . $rateId . '|' . $row['effective_date'];
                $oldHash = isset($existingHashes[$key]) ? $existingHashes[$key] : null;
                if ($oldHash !== $row['data_hash']) {
                    $rowsChanged++;
                    if ($row['room_type_id'] !== '') {
                        $changedRoomIds[$row['room_type_id']] = true;
                    }
                }
            }
            $this->upsertBatch($connection, $batch);
        }

        return new WriterResult(
            count($rows),
            $rowsChanged,
            array_keys($changedRoomIds)
        );
    }

    public function purgePastDates(SiteConfigurationDto $config): int
    {
        $connection = $this->availabilityRepository->getConnection();

        return (int)$connection->executeStatement(
            'DELETE FROM ' . AvailabilityRepository::TABLE
                . ' WHERE site_identifier = :site AND effective_date < :today',
            [
                'site' => $config->siteIdentifier,
                'today' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function loadExistingHashes(SiteConfigurationDto $config, array $rows): array
    {
        $dates = array_unique(array_column($rows, 'effective_date'));
        if ($dates === []) {
            return [];
        }

        $qb = $this->availabilityRepository->getConnection()->createQueryBuilder();
        $qb->select('room_type_id', 'rate_id', 'effective_date', 'data_hash')
            ->from(AvailabilityRepository::TABLE)
            ->where('site_identifier = :site')
            ->andWhere('effective_date IN (:dates)')
            ->setParameter('site', $config->siteIdentifier)
            ->setParameter('dates', array_values($dates), Typo3Adapter::stringArrayParameterType());

        $result = Typo3Adapter::executeQuery($qb);
        $existing = [];
        foreach (Typo3Adapter::fetchAllAssociative($result) as $row) {
            $key = $config->siteIdentifier . '|'
                . $row['room_type_id'] . '|'
                . ($row['rate_id'] ?? '') . '|'
                . $row['effective_date'];
            $existing[$key] = (string)$row['data_hash'];
        }

        return $existing;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function upsertBatch(Connection $connection, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys($rows[0]);
        $platform = $connection->getDatabasePlatform();
        $quotedColumns = array_map([$platform, 'quoteSingleIdentifier'], $columns);

        $placeholders = [];
        $params = [];
        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $col) {
                $params[] = isset($row[$col]) ? $row[$col] : null;
                $rowPlaceholders[] = '?';
            }
            $placeholders[] = '(' . implode(',', $rowPlaceholders) . ')';
        }

        $updateClauses = [];
        foreach ($columns as $col) {
            if (in_array($col, ['site_identifier', 'room_type_id', 'rate_id', 'effective_date'], true)) {
                continue;
            }
            $q = $platform->quoteSingleIdentifier($col);
            $updateClauses[] = $q . ' = VALUES(' . $q . ')';
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $platform->quoteSingleIdentifier(AvailabilityRepository::TABLE),
            implode(',', $quotedColumns),
            implode(',', $placeholders),
            implode(',', $updateClauses)
        );

        $connection->executeStatement($sql, $params);
    }
}
