<?php

namespace App\Command;

use App\Entity\RunHistory;
use App\Repository\PlaylistDefinitionRepository;
use App\Service\PlaylistRunner;
use App\Service\RunHistoryRecorder;
use App\Service\RunResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:playlist:run',
    description: 'Execute a single playlist definition (by id or name).',
)]
class RunPlaylistCommand extends Command
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
            ->addArgument('target', InputArgument::REQUIRED, 'Playlist definition id or name')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not call Subsonic, just list the tracks count')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Run even if the definition is disabled');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = (string) $input->getArgument('target');

        $def = ctype_digit($target)
            ? $this->repository->find((int) $target)
            : $this->repository->findOneByName($target);

        if ($def === null) {
            $io->error(sprintf('Playlist definition "%s" not found.', $target));
            return Command::FAILURE;
        }

        if (!$def->isEnabled() && !$input->getOption('force')) {
            $io->warning(sprintf('Playlist "%s" is disabled. Use --force to run anyway.', $def->getName()));
            return Command::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $result = $this->recorder->record(
                type: RunHistory::TYPE_PLAYLIST,
                reference: (string) $def->getId(),
                label: $def->getName(),
                action: fn () => $this->runner->run($def, $dryRun),
                extractMetrics: static fn (RunResult $r) => [
                    'tracks' => $r->trackCount,
                    'playlist_id' => $r->playlistId,
                    'dry_run' => $dryRun,
                ],
            );
            $io->success(sprintf(
                '%s "%s" → %d tracks (id=%s)',
                $dryRun ? '[DRY-RUN]' : 'Created',
                $result->playlistName,
                $result->trackCount,
                $result->playlistId,
            ));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
