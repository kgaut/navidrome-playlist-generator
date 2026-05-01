<?php

namespace App\Command;

use App\Repository\RunHistoryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:history:purge',
    description: 'Delete run_history rows older than the configured retention window.',
)]
class PurgeHistoryCommand extends Command
{
    public function __construct(
        private readonly RunHistoryRepository $repository,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Override the retention window (in days). Defaults to RUN_HISTORY_RETENTION_DAYS.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = $input->getOption('days') !== null ? max(1, (int) $input->getOption('days')) : $this->retentionDays;
        $cutoff = (new \DateTimeImmutable())->sub(new \DateInterval('P' . $days . 'D'));

        $deleted = $this->repository->purgeOlderThan($cutoff);
        $io->success(sprintf('Purged %d run_history rows older than %s.', $deleted, $cutoff->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
