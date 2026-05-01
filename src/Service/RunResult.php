<?php

namespace App\Service;

final class RunResult
{
    public function __construct(
        public readonly string $playlistId,
        public readonly string $playlistName,
        public readonly int $trackCount,
    ) {
    }
}
