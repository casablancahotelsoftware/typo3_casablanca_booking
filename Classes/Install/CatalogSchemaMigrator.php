<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Install;

use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adds catalog mirror columns when upgrading without Install Tool DB compare.
 */
final class CatalogSchemaMigrator
{
    public function migrateIfNeeded(): void
    {
        $this->ensureColumn(
            RoomTypeRepository::TABLE,
            'short_description',
            'TEXT'
        );
        $this->ensureColumn(
            RateRepository::TABLE,
            'short_description',
            'TEXT'
        );
        $this->ensureColumn(
            RateRepository::TABLE,
            'images',
            'TEXT'
        );
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns($table);

        if (!isset($columns[$column])) {
            $connection->executeStatement(
                'ALTER TABLE ' . $table . ' ADD ' . $column . ' ' . $definition
            );
        }
    }
}
