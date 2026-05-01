<?php

namespace App\Generator;

use App\Navidrome\NavidromeRepository;

class TopLastCalendarYearGenerator implements PlaylistGeneratorInterface
{
    public function __construct(private readonly NavidromeRepository $navidrome)
    {
    }

    public function getKey(): string
    {
        return 'top-last-year';
    }

    public function getLabel(): string
    {
        return 'Top de l\'année passée';
    }

    public function getDescription(): string
    {
        return 'Top de l\'année calendaire complète précédente (1er janvier au 31 décembre).';
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
        $year = (int) (new \DateTimeImmutable('now'))->format('Y');

        return [
            'from' => new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year - 1)),
            'to' => new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year)),
        ];
    }
}
