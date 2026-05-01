<?php

namespace App\Generator;

final class ParameterDefinition
{
    public const TYPE_INT = 'int';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'bool';
    public const TYPE_CHOICE = 'choice';

    /**
     * @param array<string|int, scalar> $choices For TYPE_CHOICE: value => label
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type = self::TYPE_INT,
        public readonly mixed $default = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly bool $required = true,
        public readonly array $choices = [],
        public readonly ?string $help = null,
    ) {
    }
}
