<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Configuration;

use Casablanca\CasablancaBooking\Domain\Repository\ConfigurationRepository;
use Casablanca\CasablancaBooking\Service\Scheduler\SchedulerTaskManager;

/**
 * Persists backend module configuration and ensures background sync is scheduled.
 */
class ConfigurationService
{
    /** @var ConfigurationRepository */
    private $configurationRepository;

    /** @var SchedulerTaskManager */
    private $schedulerTaskManager;

    public function __construct(
        ConfigurationRepository $configurationRepository,
        SchedulerTaskManager $schedulerTaskManager
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->schedulerTaskManager = $schedulerTaskManager;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveConfiguration(array $data): int
    {
        return $this->configurationRepository->save($data);
    }

    public function ensureDailySyncTaskAtCurrentTime(): void
    {
        $this->schedulerTaskManager->ensureDailySyncTaskAtCurrentTime();
    }
}
