<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Install;

use Casablanca\CasablancaBooking\Domain\Repository\AvailabilityRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adds rate_id column and updates the availability cache primary key when needed.
 */
final class AvailabilitySchemaMigrator
{
    public function migrateIfNeeded(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(AvailabilityRepository::TABLE);

        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns(AvailabilityRepository::TABLE);

        if (!isset($columns['rate_id'])) {
            $connection->executeStatement(
                'ALTER TABLE ' . AvailabilityRepository::TABLE
                . ' ADD rate_id VARCHAR(100) DEFAULT \'\' NOT NULL AFTER room_type_id'
            );
        }

        $indexes = $schemaManager->listTableIndexes(AvailabilityRepository::TABLE);
        $hasNewPrimary = false;
        foreach ($indexes as $index) {
            if ($index->isPrimary() && in_array('rate_id', $index->getColumns(), true)) {
                $hasNewPrimary = true;
                break;
            }
        }

        if (!$hasNewPrimary) {
            $connection->executeStatement(
                'ALTER TABLE ' . AvailabilityRepository::TABLE
                . ' DROP PRIMARY KEY, ADD PRIMARY KEY (site_identifier, room_type_id, rate_id, effective_date)'
            );
        }
    }
}
