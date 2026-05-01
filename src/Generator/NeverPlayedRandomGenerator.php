<?php

namespace App\Generator;

use App\Navidrome\NavidromeRepository;

class NeverPlayedRandomGenerator implements PlaylistGeneratorInterface
{
    public function __construct(private readonly NavidromeRepository $navidrome)
    {
    }

    public function getKey(): string
    {
        return 'never-played';
    }

    public function getLabel(): string
    {
        return 'Morceaux jamais écoutés (random)';
    }

    public function getDescription(): string
    {
        return 'Sélection aléatoire parmi les morceaux jamais joués par cet utilisateur.';
    }

    public function getParameterSchema(): array
    {
        return [];
    }

    public function generate(array $parameters, int $limit): array
    {
        return $this->navidrome->neverPlayedRandom($limit);
    }

    public function getActiveWindow(array $parameters): ?array
    {
        return null;
    }
}
