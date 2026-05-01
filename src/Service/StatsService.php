<?php

namespace App\Service;

use App\Entity\StatsSnapshot;
use App\Navidrome\NavidromeRepository;
use App\Repository\StatsSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatsService
{
    public const PERIOD_LAST_7D = '7d';
    public const PERIOD_LAST_30D = '30d';
    public const PERIOD_LAST_MONTH = 'last-month';
    public const PERIOD_LAST_YEAR = 'last-year';
    public const PERIOD_ALL_TIME = 'all-time';

    public const TOP_ARTISTS_LIMIT = 10;
    public const TOP_TRACKS_LIMIT = 50;

    /**
     * @return array<string, string> period key => human label
     */
    public static function periods(): array
    {
        return [
            self::PERIOD_LAST_7D => '7 derniers jours',
            self::PERIOD_LAST_30D => '30 derniers jours',
            self::PERIOD_LAST_MONTH => 'Mois passé',
            self::PERIOD_LAST_YEAR => 'Année passée',
            self::PERIOD_ALL_TIME => 'All-time',
        ];
    }

    public function __construct(
        private readonly NavidromeRepository $navidrome,
        private readonly StatsSnapshotRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getOrCompute(string $period): StatsSnapshot
    {
        $existing = $this->repository->findOneByPeriod($period);
        if ($existing !== null) {
            return $existing;
        }

        return $this->compute($period);
    }

    public function getCached(string $period): ?StatsSnapshot
    {
        return $this->repository->findOneByPeriod($period);
    }

    public function compute(string $period): StatsSnapshot
    {
        if (!isset(self::periods()[$period])) {
            throw new \InvalidArgumentException(sprintf('Unknown stats period "%s".', $period));
        }

        [$from, $to] = $this->resolveWindow($period);

        $data = [
            'total_plays' => $this->navidrome->getTotalPlays($from, $to),
            'distinct_tracks' => $this->navidrome->getDistinctTracksPlayed($from, $to),
            'top_artists' => $this->navidrome->getTopArtists($from, $to, self::TOP_ARTISTS_LIMIT),
            'top_tracks' => $this->navidrome->getTopTracksWithDetails($from, $to, self::TOP_TRACKS_LIMIT),
            'window_from' => $from?->format(\DateTimeInterface::ATOM),
            'window_to' => $to?->format(\DateTimeInterface::ATOM),
        ];

        $snapshot = $this->repository->findOneByPeriod($period) ?? new StatsSnapshot($period);
        $snapshot->setData($data);

        if ($snapshot->getId() === null) {
            $this->em->persist($snapshot);
        }
        $this->em->flush();

        return $snapshot;
    }

    /**
     * @return array{StatsSnapshot[], string[]} [snapshots, errors]
     */
    public function computeAll(): array
    {
        $snapshots = [];
        $errors = [];
        foreach (array_keys(self::periods()) as $period) {
            try {
                $snapshots[] = $this->compute($period);
            } catch (\Throwable $e) {
                $errors[] = sprintf('%s: %s', $period, $e->getMessage());
            }
        }

        return [$snapshots, $errors];
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function resolveWindow(string $period): array
    {
        $now = new \DateTimeImmutable('now');

        return match ($period) {
            self::PERIOD_LAST_7D => [$now->sub(new \DateInterval('P7D')), $now],
            self::PERIOD_LAST_30D => [$now->sub(new \DateInterval('P30D')), $now],
            self::PERIOD_LAST_MONTH => [
                $now->modify('first day of this month')->setTime(0, 0)->modify('-1 month'),
                $now->modify('first day of this month')->setTime(0, 0),
            ],
            self::PERIOD_LAST_YEAR => [
                new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', (int) $now->format('Y') - 1)),
                new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', (int) $now->format('Y'))),
            ],
            self::PERIOD_ALL_TIME => [null, null],
            default => throw new \InvalidArgumentException(sprintf('Unknown stats period "%s".', $period)),
        };
    }
}
