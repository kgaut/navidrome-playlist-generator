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
        $w = $this->getActiveWindow($parameters);

        return $this->navidrome->topTracksInWindow($w['from'], $w['to'], $limit);
    }

    public function getActiveWindow(array $parameters): ?array
    {
        $startOfThisMonth = (new \DateTimeImmutable('now'))->modify('first day of this month')->setTime(0, 0);

        return ['from' => $startOfThisMonth->modify('-1 month'), 'to' => $startOfThisMonth];
    }
}
