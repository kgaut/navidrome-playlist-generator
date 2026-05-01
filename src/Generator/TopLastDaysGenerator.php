<?php

namespace App\Generator;

use App\Navidrome\NavidromeRepository;

class TopLastDaysGenerator implements PlaylistGeneratorInterface
{
    public function __construct(private readonly NavidromeRepository $navidrome)
    {
    }

    public function getKey(): string
    {
        return 'top-last-days';
    }

    public function getLabel(): string
    {
        return 'Top des X derniers jours';
    }

    public function getDescription(): string
    {
        return 'Morceaux les plus écoutés sur une fenêtre glissante de N jours.';
    }

    public function getParameterSchema(): array
    {
        return [
            new ParameterDefinition(
                name: 'days',
                label: 'Nombre de jours',
                type: ParameterDefinition::TYPE_INT,
                default: 30,
                min: 1,
                max: 3650,
                help: 'Fenêtre glissante se terminant maintenant.',
            ),
        ];
    }

    public function generate(array $parameters, int $limit): array
    {
        $days = max(1, (int) ($parameters['days'] ?? 30));
        $now = new \DateTimeImmutable('now');
        $from = $now->sub(new \DateInterval('P' . $days . 'D'));

        return $this->navidrome->topTracksInWindow($from, $now, $limit);
    }
}
