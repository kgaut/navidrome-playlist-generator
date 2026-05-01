<?php

namespace App\Service;

use App\Entity\PlaylistDefinition;
use App\Generator\GeneratorRegistry;
use App\Subsonic\SubsonicClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PlaylistRunner
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly GeneratorRegistry $registry,
        private readonly SubsonicClient $subsonic,
        private readonly SettingsService $settings,
        private readonly PlaylistNameRenderer $nameRenderer,
        private readonly EntityManagerInterface $em,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function run(PlaylistDefinition $def, bool $dryRun = false): RunResult
    {
        try {
            $generator = $this->registry->get($def->getGeneratorKey());
            $limit = $def->getLimitOverride() ?? $this->settings->getDefaultLimit();
            $songIds = $generator->generate($def->getParameters(), $limit);

            $template = $def->getPlaylistNameTemplate() ?: $this->settings->getDefaultNameTemplate();
            $name = $this->nameRenderer->render($template, $generator, $def);

            if ($dryRun) {
                return new RunResult('dry-run', $name, count($songIds));
            }

            if ($songIds === []) {
                $def->setLastRunAt(new \DateTimeImmutable());
                $def->setLastRunStatus(PlaylistDefinition::STATUS_SKIPPED);
                $def->setLastRunMessage('Aucun morceau trouvé pour cette définition.');
                $this->em->flush();
                $this->logger->warning('Playlist {name} skipped (no tracks).', ['name' => $def->getName()]);

                throw new \RuntimeException('Aucun morceau trouvé pour cette définition.');
            }

            if ($def->isReplaceExisting()) {
                $existing = $this->subsonic->findPlaylistByName($name);
                if ($existing !== null) {
                    $this->subsonic->deletePlaylist($existing);
                }
            }

            $newId = $this->subsonic->createPlaylist($name, $songIds);

            $def->setLastRunAt(new \DateTimeImmutable());
            $def->setLastRunStatus(PlaylistDefinition::STATUS_SUCCESS);
            $def->setLastRunMessage(sprintf('%d morceaux ajoutés.', count($songIds)));
            $def->setLastSubsonicPlaylistId($newId);
            $this->em->flush();

            $this->logger->info('Playlist {name} created with {count} tracks (id={id}).', [
                'name' => $name, 'count' => count($songIds), 'id' => $newId,
            ]);

            return new RunResult($newId, $name, count($songIds));
        } catch (\Throwable $e) {
            $def->setLastRunAt(new \DateTimeImmutable());
            $def->setLastRunStatus(PlaylistDefinition::STATUS_ERROR);
            $def->setLastRunMessage($e->getMessage());
            $this->em->flush();
            throw $e;
        }
    }
}
