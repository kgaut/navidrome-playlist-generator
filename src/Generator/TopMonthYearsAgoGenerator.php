<?php

namespace App\Generator;

use App\Navidrome\NavidromeRepository;

class TopMonthYearsAgoGenerator implements PlaylistGeneratorInterface
{
    public function __construct(private readonly NavidromeRepository $navidrome)
    {
    }

    public function getKey(): string
    {
        return 'top-month-yago';
    }

    public function getLabel(): string
    {
        return 'Top du mois de... il y a X années';
    }

    public function getDescription(): string
    {
        return 'Top d\'un mois calendaire précis (par ex. mai) il y a X années.';
    }

    public function getParameterSchema(): array
    {
        return [
            new ParameterDefinition(
                name: 'month',
                label: 'Mois',
                type: ParameterDefinition::TYPE_CHOICE,
                default: (int) (new \DateTimeImmutable())->format('n'),
                choices: [
                    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
                ],
            ),
            new ParameterDefinition(
                name: 'years',
                label: 'Il y a combien d\'années ?',
                type: ParameterDefinition::TYPE_INT,
                default: 1,
                min: 1,
                max: 50,
            ),
        ];
    }

    public function generate(array $parameters, int $limit): array
    {
        $month = max(1, min(12, (int) ($parameters['month'] ?? 1)));
        $yearsAgo = max(1, (int) ($parameters['years'] ?? 1));
        $now = new \DateTimeImmutable('now');
        $year = (int) $now->format('Y') - $yearsAgo;

        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $end = $start->modify('+1 month');

        return $this->navidrome->topTracksInWindow($start, $end, $limit);
    }
}
