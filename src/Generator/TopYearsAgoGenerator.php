<?php

namespace App\Generator;

use App\Navidrome\NavidromeRepository;

class TopYearsAgoGenerator implements PlaylistGeneratorInterface
{
    public function __construct(private readonly NavidromeRepository $navidrome)
    {
    }

    public function getKey(): string
    {
        return 'top-years-ago';
    }

    public function getLabel(): string
    {
        return 'Top de l\'année il y a X années';
    }

    public function getDescription(): string
    {
        return 'Top de toute une année calendaire il y a X années (par ex. « top 2016 » avec X=10 en 2026).';
    }

    public function getParameterSchema(): array
    {
        return [
            new ParameterDefinition(
                name: 'years',
                label: 'Il y a combien d\'années ?',
                type: ParameterDefinition::TYPE_INT,
                default: 10,
                min: 1,
                max: 50,
            ),
        ];
    }

    public function generate(array $parameters, int $limit): array
    {
        $w = $this->getActiveWindow($parameters);

        return $this->navidrome->topTracksInWindow($w['from'], $w['to'], $limit);
    }

    public function getActiveWindow(array $parameters): ?array
    {
        $yearsAgo = max(1, (int) ($parameters['years'] ?? 10));
        $year = (int) (new \DateTimeImmutable('now'))->format('Y') - $yearsAgo;

        return [
            'from' => new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year)),
            'to' => new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year + 1)),
        ];
    }
}
