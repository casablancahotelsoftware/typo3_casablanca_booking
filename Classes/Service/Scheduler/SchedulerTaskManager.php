<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Scheduler;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Task\SyncAvailabilityTask;
use DateTimeImmutable;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Scheduler\Execution;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Creates or locates the scheduler task that runs the CASABLANCA sync once per day.
 */
class SchedulerTaskManager
{
    private const TASK_TABLE = 'tx_scheduler_task';
    private const TASK_CLASS = SyncAvailabilityTask::class;
    private const SECONDS_PER_DAY = 86400;

    /** @var ConnectionPool */
    private $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    public function ensureDailySyncTaskAtCurrentTime(): bool
    {
        $task = $this->findSyncTask();
        if (!$task instanceof SyncAvailabilityTask) {
            return $this->createDailySyncTask(new DateTimeImmutable());
        }

        if ($this->needsMigrationToDailyCron($task)) {
            return $this->applyDailyCronSchedule($task, new DateTimeImmutable());
        }

        return true;
    }

    public function findSyncTask(): ?AbstractTask
    {
        $uid = $this->findSyncTaskUid();
        if ($uid === null) {
            return null;
        }

        if (!class_exists(SchedulerTaskRepository::class)) {
            return null;
        }

        try {
            $repository = GeneralUtility::makeInstance(SchedulerTaskRepository::class);
            $task = $repository->findByUid($uid);

            return $task instanceof AbstractTask ? $task : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function needsMigrationToDailyCron(SyncAvailabilityTask $task): bool
    {
        if (!method_exists($task, 'getExecution')) {
            return false;
        }

        $execution = $task->getExecution();
        if (!$execution instanceof Execution) {
            return true;
        }

        if ($execution->getCronCmd() !== '') {
            return false;
        }

        $interval = $execution->getInterval();

        return $interval <= 0 || $interval < self::SECONDS_PER_DAY;
    }

    private function createDailySyncTask(DateTimeImmutable $scheduleTime): bool
    {
        if (!class_exists(SchedulerTaskRepository::class)) {
            return false;
        }

        /** @var SyncAvailabilityTask $task */
        $task = GeneralUtility::makeInstance(SyncAvailabilityTask::class);
        $task->setDisabled(false);
        $this->registerDailyCronSchedule($task, $scheduleTime);

        $repository = GeneralUtility::makeInstance(SchedulerTaskRepository::class);

        return $repository->add($task);
    }

    private function applyDailyCronSchedule(SyncAvailabilityTask $task, DateTimeImmutable $scheduleTime): bool
    {
        $this->registerDailyCronSchedule($task, $scheduleTime);
        if (method_exists($task, 'save')) {
            $task->save();
        }

        return true;
    }

    private function registerDailyCronSchedule(SyncAvailabilityTask $task, DateTimeImmutable $scheduleTime): void
    {
        if (!method_exists($task, 'registerRecurringExecution')) {
            return;
        }

        $task->registerRecurringExecution(
            time(),
            0,
            0,
            false,
            $this->buildDailyCronCommand($scheduleTime)
        );
    }

    private function buildDailyCronCommand(DateTimeImmutable $scheduleTime): string
    {
        return sprintf(
            '%d %d * * *',
            (int)$scheduleTime->format('i'),
            (int)$scheduleTime->format('G')
        );
    }

    private function findSyncTaskUid(): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TASK_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(
            GeneralUtility::makeInstance(DeletedRestriction::class)
        );

        $queryBuilder
            ->select('uid')
            ->from(self::TASK_TABLE)
            ->setMaxResults(1);

        if ($this->hasSchedulerTaskTypeColumn()) {
            $queryBuilder->where(
                $queryBuilder->expr()->eq(
                    'tasktype',
                    $queryBuilder->createNamedParameter(self::TASK_CLASS)
                )
            );
        } else {
            $needle = '%' . str_replace('\\', '\\\\', self::TASK_CLASS) . '%';
            $queryBuilder->where(
                $queryBuilder->expr()->like(
                    'serialized_task_object',
                    $queryBuilder->createNamedParameter($needle)
                )
            );
        }

        $result = Typo3Adapter::executeQuery($queryBuilder);
        $rows = Typo3Adapter::fetchAllAssociative($result);
        $row = $rows[0] ?? false;

        if ($row === false) {
            return null;
        }

        return (int)$row['uid'];
    }

    private function hasSchedulerTaskTypeColumn(): bool
    {
        try {
            $connection = $this->connectionPool->getConnectionForTable(self::TASK_TABLE);
            $columns = $connection->createSchemaManager()->listTableColumns(self::TASK_TABLE);

            return isset($columns['tasktype']);
        } catch (\Throwable) {
            return Typo3Adapter::isAtLeast(14);
        }
    }
}
