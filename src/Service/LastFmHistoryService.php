<?php

namespace App\Service;

use App\Entity\LastFmHistoryEntry;
use App\LastFm\LastFmClient;
use App\Repository\LastFmHistoryEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

class LastFmHistoryService
{
    public const DEFAULT_LIMIT = 100;

    public function __construct(
        private readonly LastFmClient $client,
        private readonly LastFmHistoryEntryRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Fetch the most recent $limit scrobbles for $user from the Last.fm API,
     * wipe the cached entries for this user and replace them in a single
     * transaction. Returns the number of rows inserted.
     */
    public function refresh(string $apiKey, string $user, int $limit = self::DEFAULT_LIMIT): int
    {
        $limit = max(1, min(1000, $limit));

        $entries = [];
        foreach ($this->client->streamRecentTracks($apiKey, $user) as $scrobble) {
            $entries[] = new LastFmHistoryEntry(
                lastfmUser: $user,
                playedAt: $scrobble->playedAt,
                artist: $scrobble->artist,
                title: $scrobble->title,
                album: $scrobble->album,
                mbid: $scrobble->mbid,
            );
            if (count($entries) >= $limit) {
                break;
            }
        }

        $this->em->wrapInTransaction(function () use ($user, $entries): void {
            $this->repository->deleteForUser($user);
            foreach ($entries as $e) {
                $this->em->persist($e);
            }
            $this->em->flush();
        });

        return count($entries);
    }
}
