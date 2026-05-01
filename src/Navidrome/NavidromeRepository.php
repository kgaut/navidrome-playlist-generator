<?php

namespace App\Navidrome;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;

class NavidromeRepository
{
    private ?Connection $connection = null;
    private ?bool $hasScrobblesCache = null;
    private ?string $userIdCache = null;

    public function __construct(
        private readonly string $dbPath,
        private readonly string $userName,
    ) {
    }

    public function isAvailable(): bool
    {
        try {
            $this->connection()->executeQuery('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function hasScrobblesTable(): bool
    {
        if ($this->hasScrobblesCache !== null) {
            return $this->hasScrobblesCache;
        }

        $row = $this->connection()->fetchOne(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='scrobbles'"
        );

        return $this->hasScrobblesCache = ($row !== false);
    }

    public function resolveUserId(): string
    {
        if ($this->userIdCache !== null) {
            return $this->userIdCache;
        }

        $id = $this->connection()->fetchOne(
            'SELECT id FROM user WHERE user_name = :name LIMIT 1',
            ['name' => $this->userName],
        );
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException(sprintf(
                'Navidrome user "%s" not found in database. Check NAVIDROME_USER.',
                $this->userName,
            ));
        }

        return $this->userIdCache = $id;
    }

    /**
     * Top tracks within [from, to). Uses scrobbles when available, otherwise
     * falls back to annotation.play_date.
     *
     * @return string[] media_file ids ordered by play count DESC
     */
    public function topTracksInWindow(\DateTimeInterface $from, \DateTimeInterface $to, int $limit): array
    {
        $userId = $this->resolveUserId();
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        if ($this->hasScrobblesTable()) {
            $sql = <<<'SQL'
                SELECT s.media_file_id AS id
                FROM scrobbles s
                WHERE s.user_id = :uid
                  AND s.submission_time >= :from
                  AND s.submission_time <  :to
                GROUP BY s.media_file_id
                ORDER BY COUNT(*) DESC, MAX(s.submission_time) DESC
                LIMIT :lim
            SQL;
        } else {
            $sql = <<<'SQL'
                SELECT a.item_id AS id
                FROM annotation a
                WHERE a.item_type = 'media_file'
                  AND a.user_id = :uid
                  AND a.play_date >= :from
                  AND a.play_date <  :to
                ORDER BY a.play_count DESC, a.play_date DESC
                LIMIT :lim
            SQL;
        }

        $rows = $this->connection()->fetchAllAssociative($sql, [
            'uid' => $userId,
            'from' => $fromStr,
            'to' => $toStr,
            'lim' => $limit,
        ], [
            'lim' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        return array_map(static fn (array $r) => (string) $r['id'], $rows);
    }

    /**
     * All-time top: based on annotation.play_count (always available).
     *
     * @return string[]
     */
    public function topAllTime(int $limit): array
    {
        $userId = $this->resolveUserId();
        $sql = <<<'SQL'
            SELECT a.item_id AS id
            FROM annotation a
            WHERE a.item_type = 'media_file'
              AND a.user_id = :uid
              AND a.play_count > 0
            ORDER BY a.play_count DESC, a.play_date DESC
            LIMIT :lim
        SQL;

        $rows = $this->connection()->fetchAllAssociative($sql, [
            'uid' => $userId,
            'lim' => $limit,
        ], [
            'lim' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        return array_map(static fn (array $r) => (string) $r['id'], $rows);
    }

    /**
     * Random media files never played by the configured user.
     *
     * @return string[]
     */
    public function neverPlayedRandom(int $limit): array
    {
        $userId = $this->resolveUserId();
        $sql = <<<'SQL'
            SELECT mf.id
            FROM media_file mf
            LEFT JOIN annotation a
              ON a.item_id = mf.id
             AND a.item_type = 'media_file'
             AND a.user_id = :uid
            WHERE COALESCE(a.play_count, 0) = 0
            ORDER BY RANDOM()
            LIMIT :lim
        SQL;

        $rows = $this->connection()->fetchAllAssociative($sql, [
            'uid' => $userId,
            'lim' => $limit,
        ], [
            'lim' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        return array_map(static fn (array $r) => (string) $r['id'], $rows);
    }

    /**
     * Total number of plays in [from, to). Pass null/null for all-time.
     */
    public function getTotalPlays(?\DateTimeInterface $from, ?\DateTimeInterface $to): int
    {
        $userId = $this->resolveUserId();

        if ($this->hasScrobblesTable() && $from !== null && $to !== null) {
            $sql = 'SELECT COUNT(*) FROM scrobbles
                    WHERE user_id = :uid
                      AND submission_time >= :f AND submission_time < :t';
            $count = $this->connection()->fetchOne($sql, [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
            ]);

            return (int) $count;
        }

        if ($from !== null && $to !== null) {
            // Fallback: only counts plays whose LAST play falls in the window.
            $sql = "SELECT COALESCE(SUM(play_count), 0) FROM annotation
                    WHERE item_type = 'media_file' AND user_id = :uid
                      AND play_date >= :f AND play_date < :t";

            return (int) $this->connection()->fetchOne($sql, [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
            ]);
        }

        $sql = "SELECT COALESCE(SUM(play_count), 0) FROM annotation
                WHERE item_type = 'media_file' AND user_id = :uid";

        return (int) $this->connection()->fetchOne($sql, ['uid' => $userId]);
    }

    /**
     * Number of distinct media files played at least once in [from, to).
     */
    public function getDistinctTracksPlayed(?\DateTimeInterface $from, ?\DateTimeInterface $to): int
    {
        $userId = $this->resolveUserId();

        if ($this->hasScrobblesTable() && $from !== null && $to !== null) {
            $sql = 'SELECT COUNT(DISTINCT media_file_id) FROM scrobbles
                    WHERE user_id = :uid
                      AND submission_time >= :f AND submission_time < :t';

            return (int) $this->connection()->fetchOne($sql, [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
            ]);
        }

        if ($from !== null && $to !== null) {
            $sql = "SELECT COUNT(*) FROM annotation
                    WHERE item_type = 'media_file' AND user_id = :uid
                      AND play_count > 0
                      AND play_date >= :f AND play_date < :t";

            return (int) $this->connection()->fetchOne($sql, [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
            ]);
        }

        $sql = "SELECT COUNT(*) FROM annotation
                WHERE item_type = 'media_file' AND user_id = :uid AND play_count > 0";

        return (int) $this->connection()->fetchOne($sql, ['uid' => $userId]);
    }

    /**
     * Top artists by aggregated plays in [from, to). Pass null/null for all-time.
     *
     * @return list<array{artist: string, plays: int}>
     */
    public function getTopArtists(?\DateTimeInterface $from, ?\DateTimeInterface $to, int $limit): array
    {
        $userId = $this->resolveUserId();

        if ($this->hasScrobblesTable() && $from !== null && $to !== null) {
            $sql = 'SELECT mf.artist AS artist, COUNT(*) AS plays
                    FROM scrobbles s
                    JOIN media_file mf ON mf.id = s.media_file_id
                    WHERE s.user_id = :uid
                      AND s.submission_time >= :f AND s.submission_time < :t
                      AND mf.artist != ""
                    GROUP BY mf.artist
                    ORDER BY plays DESC, artist ASC
                    LIMIT :lim';
            $params = [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
                'lim' => $limit,
            ];
        } elseif ($from !== null && $to !== null) {
            $sql = 'SELECT mf.artist AS artist, COALESCE(SUM(a.play_count), 0) AS plays
                    FROM annotation a
                    JOIN media_file mf ON mf.id = a.item_id
                    WHERE a.item_type = "media_file" AND a.user_id = :uid
                      AND a.play_date >= :f AND a.play_date < :t
                      AND mf.artist != ""
                    GROUP BY mf.artist
                    ORDER BY plays DESC, artist ASC
                    LIMIT :lim';
            $params = [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
                'lim' => $limit,
            ];
        } else {
            $sql = 'SELECT mf.artist AS artist, COALESCE(SUM(a.play_count), 0) AS plays
                    FROM annotation a
                    JOIN media_file mf ON mf.id = a.item_id
                    WHERE a.item_type = "media_file" AND a.user_id = :uid
                      AND a.play_count > 0
                      AND mf.artist != ""
                    GROUP BY mf.artist
                    ORDER BY plays DESC, artist ASC
                    LIMIT :lim';
            $params = ['uid' => $userId, 'lim' => $limit];
        }

        $rows = $this->connection()->fetchAllAssociative($sql, $params, [
            'lim' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        return array_map(
            static fn (array $r) => ['artist' => (string) $r['artist'], 'plays' => (int) $r['plays']],
            $rows,
        );
    }

    /**
     * Top tracks (with full metadata) by aggregated plays in [from, to).
     * Pass null/null for all-time.
     *
     * @return list<array{id: string, title: string, artist: string, album: string, plays: int}>
     */
    public function getTopTracksWithDetails(?\DateTimeInterface $from, ?\DateTimeInterface $to, int $limit): array
    {
        $userId = $this->resolveUserId();

        if ($this->hasScrobblesTable() && $from !== null && $to !== null) {
            $sql = 'SELECT mf.id AS id, mf.title AS title, mf.artist AS artist, mf.album AS album,
                           COUNT(*) AS plays
                    FROM scrobbles s
                    JOIN media_file mf ON mf.id = s.media_file_id
                    WHERE s.user_id = :uid
                      AND s.submission_time >= :f AND s.submission_time < :t
                    GROUP BY mf.id, mf.title, mf.artist, mf.album
                    ORDER BY plays DESC, title ASC
                    LIMIT :lim';
            $params = [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
                'lim' => $limit,
            ];
        } elseif ($from !== null && $to !== null) {
            $sql = 'SELECT mf.id AS id, mf.title AS title, mf.artist AS artist, mf.album AS album,
                           COALESCE(a.play_count, 0) AS plays
                    FROM annotation a
                    JOIN media_file mf ON mf.id = a.item_id
                    WHERE a.item_type = "media_file" AND a.user_id = :uid
                      AND a.play_date >= :f AND a.play_date < :t
                    ORDER BY plays DESC, title ASC
                    LIMIT :lim';
            $params = [
                'uid' => $userId,
                'f' => $from->format('Y-m-d H:i:s'),
                't' => $to->format('Y-m-d H:i:s'),
                'lim' => $limit,
            ];
        } else {
            $sql = 'SELECT mf.id AS id, mf.title AS title, mf.artist AS artist, mf.album AS album,
                           a.play_count AS plays
                    FROM annotation a
                    JOIN media_file mf ON mf.id = a.item_id
                    WHERE a.item_type = "media_file" AND a.user_id = :uid
                      AND a.play_count > 0
                    ORDER BY plays DESC, title ASC
                    LIMIT :lim';
            $params = ['uid' => $userId, 'lim' => $limit];
        }

        $rows = $this->connection()->fetchAllAssociative($sql, $params, [
            'lim' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        return array_map(static fn (array $r) => [
            'id' => (string) $r['id'],
            'title' => (string) $r['title'],
            'artist' => (string) $r['artist'],
            'album' => (string) $r['album'],
            'plays' => (int) $r['plays'],
        ], $rows);
    }

    /**
     * Resolve a list of media_file ids to TrackSummary[], preserving order.
     *
     * @param string[] $ids
     *
     * @return TrackSummary[]
     */
    public function summarize(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $userId = $this->resolveUserId();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT mf.id, mf.title, mf.artist, mf.album, mf.duration,
                    COALESCE(a.play_count, 0) AS plays
             FROM media_file mf
             LEFT JOIN annotation a
               ON a.item_id = mf.id AND a.item_type=\'media_file\' AND a.user_id = ?
             WHERE mf.id IN (%s)',
            $placeholders,
        );

        $rows = $this->connection()->fetchAllAssociative($sql, array_merge([$userId], $ids));
        $byId = [];
        foreach ($rows as $r) {
            $byId[(string) $r['id']] = new TrackSummary(
                id: (string) $r['id'],
                title: (string) ($r['title'] ?? ''),
                artist: (string) ($r['artist'] ?? ''),
                album: (string) ($r['album'] ?? ''),
                duration: (int) ($r['duration'] ?? 0),
                plays: (int) ($r['plays'] ?? 0),
            );
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }

        return $out;
    }

    private function connection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        try {
            // We rely on the Docker volume being mounted read-only (`:ro`)
            // for safety. We do not open the DB in SQLite read-only mode
            // because that prevents seeing concurrent writes from Navidrome
            // on some platforms.
            $this->connection = DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'path' => $this->dbPath,
                'driverOptions' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ],
            ]);
        } catch (DbalException $e) {
            throw new \RuntimeException('Cannot open Navidrome database at ' . $this->dbPath . ': ' . $e->getMessage(), 0, $e);
        }

        return $this->connection;
    }
}
