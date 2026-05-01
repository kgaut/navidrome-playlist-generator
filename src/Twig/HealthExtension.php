<?php

namespace App\Twig;

use App\Service\HealthChecker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HealthExtension extends AbstractExtension
{
    public function __construct(private readonly HealthChecker $checker)
    {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_health', $this->snapshot(...)),
        ];
    }

    /**
     * @return array{navidrome_db: bool, scrobbles: bool, subsonic: bool, healthy: bool}
     */
    public function snapshot(): array
    {
        return $this->checker->snapshot();
    }
}
