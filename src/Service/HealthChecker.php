<?php

namespace App\Service;

use App\Navidrome\NavidromeRepository;
use App\Subsonic\SubsonicClient;
use Psr\Cache\CacheItemPoolInterface;

final class HealthChecker
{
    private const CACHE_KEY = 'app.health.snapshot';

    private const CACHE_TTL = 60;

    /**
     * @var array{navidrome_db: bool, scrobbles: bool, subsonic: bool, healthy: bool}|null
     */
    private ?array $memoized = null;

    public function __construct(
        private readonly NavidromeRepository $navidrome,
        private readonly SubsonicClient $subsonic,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array{navidrome_db: bool, scrobbles: bool, subsonic: bool, healthy: bool}
     */
    public function snapshot(): array
    {
        if ($this->memoized !== null) {
            return $this->memoized;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            /** @var array{navidrome_db: bool, scrobbles: bool, subsonic: bool, healthy: bool} $cached */
            $cached = $item->get();

            return $this->memoized = $cached;
        }

        $navidromeDb = $this->navidrome->isAvailable();
        $scrobbles = $navidromeDb && $this->navidrome->hasScrobblesTable();
        $subsonic = $this->subsonic->ping();
        $payload = [
            'navidrome_db' => $navidromeDb,
            'scrobbles' => $scrobbles,
            'subsonic' => $subsonic,
            'healthy' => $navidromeDb && $subsonic,
        ];

        $item->set($payload);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $this->memoized = $payload;
    }
}
