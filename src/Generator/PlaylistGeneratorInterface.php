<?php

namespace App\Generator;

interface PlaylistGeneratorInterface
{
    /**
     * Stable kebab-case identifier persisted in the database.
     */
    public function getKey(): string;

    /**
     * Human label shown in the UI.
     */
    public function getLabel(): string;

    /**
     * One-line description shown next to the label.
     */
    public function getDescription(): string;

    /**
     * Schema of accepted parameters. Used to build the form and validate
     * persisted values.
     *
     * @return ParameterDefinition[]
     */
    public function getParameterSchema(): array;

    /**
     * Run the generator and return ordered media_file ids (max $limit items).
     *
     * @param array<string, mixed> $parameters
     *
     * @return string[]
     */
    public function generate(array $parameters, int $limit): array;

    /**
     * Time window the generator operates on, or null when the generator
     * is lifetime-based / window-less (top-all-time, never-played,
     * songs-you-used-to-love…). When non-null, callers can use it to
     * scope downstream queries — e.g. counting plays per track within
     * the same window for the preview UI.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array{from: \DateTimeImmutable, to: \DateTimeImmutable}|null
     */
    public function getActiveWindow(array $parameters): ?array;
}
