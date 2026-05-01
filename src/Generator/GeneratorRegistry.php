<?php

namespace App\Generator;

class GeneratorRegistry
{
    /**
     * @var array<string, PlaylistGeneratorInterface>
     */
    private array $byKey = [];

    /**
     * @param iterable<PlaylistGeneratorInterface> $generators
     */
    public function __construct(iterable $generators)
    {
        foreach ($generators as $generator) {
            $key = $generator->getKey();
            if (isset($this->byKey[$key])) {
                throw new \LogicException(sprintf(
                    'Duplicate playlist generator key "%s" (already registered by %s).',
                    $key,
                    $this->byKey[$key]::class,
                ));
            }
            $this->byKey[$key] = $generator;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->byKey[$key]);
    }

    public function get(string $key): PlaylistGeneratorInterface
    {
        if (!isset($this->byKey[$key])) {
            throw new \InvalidArgumentException(sprintf('Unknown playlist generator "%s".', $key));
        }

        return $this->byKey[$key];
    }

    /**
     * @return array<string, PlaylistGeneratorInterface>
     */
    public function all(): array
    {
        return $this->byKey;
    }

    /**
     * @return array<string, string> key => label, sorted by label
     */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->byKey as $key => $gen) {
            $choices[$gen->getLabel()] = $key;
        }
        ksort($choices);

        return $choices;
    }
}
