<?php

namespace App\Service;

use App\Entity\NavidromeHistoryEntry;
use App\Navidrome\NavidromeRepository;
use App\Repository\NavidromeHistoryEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

class NavidromeHistoryService
{
    public const DEFAULT_LIMIT = 100;

    public function __construct(
        private readonly NavidromeRepository $navidrome,
        private readonly NavidromeHistoryEntryRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Snapshot the latest $limit scrobbles from the Navidrome `scrobbles`
     * table into the local cache (wipe + re-insert in a transaction).
     * Returns the number of rows inserted.
     */
    public function refresh(int $limit = self::DEFAULT_LIMIT): int
    {
        $limit = max(1, min(1000, $limit));
        $rows = $this->navidrome->getRecentScrobbles($limit);

        $entries = array_map(
            static fn (array $r): NavidromeHistoryEntry => new NavidromeHistoryEntry(
                mediaFileId: $r['media_file_id'],
                playedAt: $r['played_at'],
                artist: $r['artist'],
                title: $r['title'],
                album: $r['album'],
            ),
            $rows,
        );

        $this->em->wrapInTransaction(function () use ($entries): void {
            $this->repository->deleteAll();
            foreach ($entries as $e) {
                $this->em->persist($e);
            }
            $this->em->flush();
        });

        return count($entries);
    }
}
