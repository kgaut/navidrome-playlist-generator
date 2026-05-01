<?php

namespace App\Service;

use App\Entity\StatsSnapshot;
use App\Navidrome\NavidromeRepository;
use App\Repository\StatsSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

class WrappedService
{
    public const TOP_ARTISTS = 25;

    public const TOP_TRACKS = 50;

    public const NEW_ARTISTS = 25;

    public function __construct(
        private readonly NavidromeRepository $navidrome,
        private readonly StatsSnapshotRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getCached(int $year): ?StatsSnapshot
    {
        return $this->repository->findOneByPeriod($this->periodKey($year));
    }

    /**
     * (Re)compute the wrapped snapshot for $year and persist it as a
     * StatsSnapshot row keyed by 'wrapped-<year>'.
     */
    public function compute(int $year): StatsSnapshot
    {
        $start = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year));
        $end = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year + 1));

        $totalPlays = $this->navidrome->getTotalPlays($start, $end);
        $distinctTracks = $this->navidrome->getDistinctTracksPlayed($start, $end);
        $topArtists = $this->navidrome->getTopArtists($start, $end, self::TOP_ARTISTS);
        $topTracks = $this->navidrome->getTopTracksWithDetails($start, $end, self::TOP_TRACKS);
        $newArtists = $this->navidrome->getNewArtists($year, self::NEW_ARTISTS);
        $streak = $this->navidrome->getLongestListeningStreak($year);
        $mostActiveMonth = $this->navidrome->getMostActiveMonth($year);
        $totalSeconds = $this->totalSecondsFromTopTracks($topTracks, $totalPlays);

        $data = [
            'total_plays' => $totalPlays,
            'distinct_tracks' => $distinctTracks,
            // unused columns (kept to share schema with other snapshots)
            'top_artists' => array_map(
                static fn (array $r) => ['artist' => $r['artist'], 'plays' => $r['plays']],
                $topArtists,
            ),
            'top_tracks' => $topTracks,
            'window_from' => $start->format(\DateTimeInterface::ATOM),
            'window_to' => $end->format(\DateTimeInterface::ATOM),
            // wrapped-specific
            'wrapped_year' => $year,
            'wrapped_total_seconds_estimate' => $totalSeconds,
            'wrapped_new_artists' => $newArtists,
            'wrapped_streak_days' => $streak,
            'wrapped_most_active_month' => $mostActiveMonth,
        ];

        $snapshot = $this->repository->findOneByPeriod($this->periodKey($year)) ?? new StatsSnapshot($this->periodKey($year));
        $snapshot->setData($data);
        if ($snapshot->getId() === null) {
            $this->em->persist($snapshot);
        }
        $this->em->flush();

        return $snapshot;
    }

    private function periodKey(int $year): string
    {
        return 'wrapped-' . $year;
    }

    /**
     * Estimate the total seconds listened: sum of (track duration * its plays
     * within the window) over the top tracks, then extrapolated to total
     * plays via the captured ratio. Cheap approximation that avoids loading
     * every scrobble row.
     *
     * @param list<array{id: string, title: string, artist: string, album: string, plays: int}> $topTracks
     */
    private function totalSecondsFromTopTracks(array $topTracks, int $totalPlays): int
    {
        if ($topTracks === [] || $totalPlays === 0) {
            return 0;
        }

        $ids = array_column($topTracks, 'id');
        $summaries = $this->navidrome->summarize($ids);
        $durationById = [];
        foreach ($summaries as $s) {
            $durationById[$s->id] = $s->duration;
        }

        $playsTracked = 0;
        $secondsTracked = 0;
        foreach ($topTracks as $row) {
            $playsTracked += $row['plays'];
            $secondsTracked += ($durationById[$row['id']] ?? 0) * $row['plays'];
        }

        if ($playsTracked === 0) {
            return 0;
        }

        return (int) round($secondsTracked * ($totalPlays / $playsTracked));
    }
}
