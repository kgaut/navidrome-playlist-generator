<?php

namespace App\Command;

use App\Entity\RunHistory;
use App\Repository\PlaylistDefinitionRepository;
use App\Service\PlaylistRunner;
use App\Service\RunHistoryRecorder;
use App\Service\RunResult;
use Cron\CronExpression;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:playlist:run-all',
    description: 'Run all enabled playlist definitions whose schedule is due.',
)]
class RunAllPlaylistsCommand extends Command
{
    public function __construct(
        private readonly PlaylistDefinitionRepository $repository,
        private readonly PlaylistRunner $runner,
        private readonly RunHistoryRecorder $recorder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Run every enabled definition regardless of schedule')
            ->addOption('window', null, InputOption::VALUE_REQUIRED, 'Look-back window in seconds for "due" check', '300');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $all = (bool) $input->getOption('all');
        $window = max(60, (int) $input->getOption('window'));
        $now = new \DateTimeImmutable('now');

        $definitions = $this->repository->findBy(['enabled' => true], ['name' => 'ASC']);
        $ran = 0;
        $errors = 0;

        foreach ($definitions as $def) {
            if (!$all) {
                $schedule = $def->getSchedule();
                if (!$schedule) {
                    continue;
                }
                try {
                    $expr = new CronExpression($schedule);
                    $previous = $expr->getPreviousRunDate($now);
                    if ($previous->getTimestamp() < $now->getTimestamp() - $window) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    $io->warning(sprintf('Invalid schedule for "%s": %s', $def->getName(), $e->getMessage()));
                    continue;
                }
            }

            try {
                $result = $this->recorder->record(
                    type: RunHistory::TYPE_PLAYLIST,
                    reference: (string) $def->getId(),
                    label: $def->getName(),
                    action: fn () => $this->runner->run($def),
                    extractMetrics: static fn (RunResult $r) => [
                        'tracks' => $r->trackCount,
                        'playlist_id' => $r->playlistId,
                    ],
                );
                $io->writeln(sprintf(
                    '<info>OK</info> %s → %d tracks',
                    $result->playlistName,
                    $result->trackCount,
                ));
                $ran++;
            } catch (\Throwable $e) {
                $io->writeln(sprintf('<error>KO</error> %s : %s', $def->getName(), $e->getMessage()));
                $errors++;
            }
        }

        $io->newLine();
        $io->writeln(sprintf('Done. %d ran, %d errors.', $ran, $errors));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
