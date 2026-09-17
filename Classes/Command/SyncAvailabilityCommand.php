<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Command;

use Casablanca\CasablancaBooking\Service\Sync\SyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI entry point — delegates all work to SyncService.
 */
class SyncAvailabilityCommand extends Command
{
    /** @var SyncService */
    private $syncService;

    public function __construct(SyncService $syncService)
    {
        parent::__construct('casablanca-booking:sync');
        $this->syncService = $syncService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Synchronise CASABLANCA availability, rates and inventory into the local cache.')
            ->addOption(
                'site',
                's',
                InputOption::VALUE_REQUIRED,
                'Restrict the sync to a single TYPO3 site identifier.'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Ignore data hashes and flush the site-wide cache tag.'
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Override the sync window in days.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('CASABLANCA Booking — ARI Sync');

        $site = (string)$input->getOption('site');
        $force = (bool)$input->getOption('force');
        $daysOption = $input->getOption('days');
        $days = $daysOption !== null ? (int)$daysOption : null;

        return $this->syncService->sync(
            $site !== '' ? $site : null,
            $force,
            $days
        );
    }
}
