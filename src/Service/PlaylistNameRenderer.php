<?php

namespace App\Service;

use App\Entity\PlaylistDefinition;
use App\Generator\PlaylistGeneratorInterface;

class PlaylistNameRenderer
{
    public function render(
        string $template,
        PlaylistGeneratorInterface $generator,
        PlaylistDefinition $definition,
        ?\DateTimeImmutable $now = null,
    ): string {
        $now ??= new \DateTimeImmutable();

        $params = $definition->getParameters();

        $replacements = [
            '{date}' => $now->format('Y-m-d'),
            '{datetime}' => $now->format('Y-m-d H:i'),
            '{month}' => $now->format('Y-m'),
            '{year}' => $now->format('Y'),
            '{label}' => $generator->getLabel(),
            '{name}' => $definition->getName(),
            '{preset}' => $generator->getKey(),
        ];

        foreach ($params as $k => $v) {
            if (is_scalar($v)) {
                $replacements['{param:' . $k . '}'] = (string) $v;
            }
        }

        return strtr($template, $replacements);
    }
}
