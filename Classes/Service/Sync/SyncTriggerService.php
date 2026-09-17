<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Repository\ConfigurationRepository;
use Psr\Log\LoggerInterface;

/**
 * Central entry point for non-manual sync triggers with re-entrancy protection.
 */
final class SyncTriggerService
{
    /** @var bool */
    private static $syncInProgress = false;

    /** @var SyncService */
    private $syncService;

    /** @var ConfigurationRepository */
    private $configurationRepository;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        SyncService $syncService,
        ConfigurationRepository $configurationRepository,
        LoggerInterface $logger
    ) {
        $this->syncService = $syncService;
        $this->configurationRepository = $configurationRepository;
        $this->logger = $logger;
    }

    public function triggerSync(string $source, ?string $siteIdentifier = null): bool
    {
        if (self::$syncInProgress) {
            return false;
        }

        if ($this->configurationRepository->findAll() === []) {
            return false;
        }

        self::$syncInProgress = true;
        try {
            $this->logger->info(
                'CASABLANCA sync triggered by {source}',
                [
                    'source' => $source,
                    'site' => $siteIdentifier,
                ]
            );

            return $this->syncService->sync($siteIdentifier, false, null) === 0;
        } finally {
            self::$syncInProgress = false;
        }
    }
}
