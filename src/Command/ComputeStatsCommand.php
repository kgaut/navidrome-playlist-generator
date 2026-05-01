<?php

namespace App\Command;

use App\Entity\RunHistory;
use App\Entity\StatsSnapshot;
use App\Service\RunHistoryRecorder;
use App\Service\StatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stats:compute',
    description: 'Recompute statistics snapshots cached in the local DB.',
)]
class ComputeStatsCommand extends Command
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly RunHistoryRecorder $recorder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'period',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('Compute a single period (one of: %s). Omit to compute them all.', implode(', ', array_keys(StatsService::periods()))),
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $period = $input->getOption('period');

        if ($period !== null) {
            $period = (string) $period;
            try {
                $snapshot = $this->recorder->record(
                    type: RunHistory::TYPE_STATS,
                    reference: $period,
                    label: 'Stats — ' . $period,
                    action: fn () => $this->stats->compute($period),
                    extractMetrics: static fn (StatsSnapshot $s) => [
                        'total_plays' => $s->getData()['total_plays'],
                        'distinct_tracks' => $s->getData()['distinct_tracks'],
                    ],
                );
                $io->success(sprintf(
                    'Period "%s" computed: %d plays, %d distinct tracks.',
                    $period,
                    $snapshot->getData()['total_plays'],
                    $snapshot->getData()['distinct_tracks'],
                ));

                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        $snapshots = [];
        $errors = [];
        foreach (array_keys(StatsService::periods()) as $p) {
            try {
                $snapshot = $this->recorder->record(
                    type: RunHistory::TYPE_STATS,
                    reference: $p,
                    label: 'Stats — ' . $p,
                    action: fn () => $this->stats->compute($p),
                    extractMetrics: static fn (StatsSnapshot $s) => [
                        'total_plays' => $s->getData()['total_plays'],
                        'distinct_tracks' => $s->getData()['distinct_tracks'],
                    ],
                );
                $snapshots[] = $snapshot;
            } catch (\Throwable $e) {
                $errors[] = sprintf('%s: %s', $p, $e->getMessage());
            }
        }

        foreach ($snapshots as $snapshot) {
            $io->writeln(sprintf(
                '<info>OK</info> %s — %d plays, %d distinct tracks',
                $snapshot->getPeriod(),
                $snapshot->getData()['total_plays'],
                $snapshot->getData()['distinct_tracks'],
            ));
        }

        foreach ($errors as $error) {
            $io->writeln(sprintf('<error>KO</error> %s', $error));
        }

        $io->newLine();
        $io->writeln(sprintf('Done. %d ok, %d errors.', count($snapshots), count($errors)));

        return $errors === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
