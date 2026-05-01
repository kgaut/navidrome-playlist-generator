<?php

namespace App\Generator;

use App\Navidrome\NavidromeRepository;

class TopLastCalendarMonthGenerator implements PlaylistGeneratorInterface
{
    public function __construct(private readonly NavidromeRepository $navidrome)
    {
    }

    public function getKey(): string
    {
        return 'top-last-month';
    }

    public function getLabel(): string
    {
        return 'Top du mois calendaire passé';
    }

    public function getDescription(): string
    {
        return 'Top du mois calendaire complet précédent (1er → dernier jour).';
    }

    public function getParameterSchema(): array
    {
        return [];
    }

    public function generate(array $parameters, int $limit): array
    {
        $now = new \DateTimeImmutable('now');
        $startOfThisMonth = $now->modify('first day of this month')->setTime(0, 0);
        $startOfLastMonth = $startOfThisMonth->modify('-1 month');

        return $this->navidrome->topTracksInWindow($startOfLastMonth, $startOfThisMonth, $limit);
    }
}
