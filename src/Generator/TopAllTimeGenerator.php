<?php

namespace App\Generator;

use App\Navidrome\NavidromeRepository;

class TopAllTimeGenerator implements PlaylistGeneratorInterface
{
    public function __construct(private readonly NavidromeRepository $navidrome)
    {
    }

    public function getKey(): string
    {
        return 'top-all-time';
    }

    public function getLabel(): string
    {
        return 'Top all-time';
    }

    public function getDescription(): string
    {
        return 'Morceaux les plus écoutés depuis toujours (basé sur le compteur global).';
    }

    public function getParameterSchema(): array
    {
        return [];
    }

    public function generate(array $parameters, int $limit): array
    {
        return $this->navidrome->topAllTime($limit);
    }

    public function getActiveWindow(array $parameters): ?array
    {
        return null;
    }
}
