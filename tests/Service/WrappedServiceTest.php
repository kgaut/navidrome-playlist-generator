<?php

namespace App\Tests\Service;

use App\Navidrome\NavidromeRepository;
use App\Repository\StatsSnapshotRepository;
use App\Service\WrappedService;
use App\Tests\Navidrome\NavidromeFixtureFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class WrappedServiceTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/navidrome-wrap-' . uniqid() . '.db';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testComputePopulatesAllSections(): void
    {
        $conn = NavidromeFixtureFactory::createDatabase($this->dbPath, withScrobbles: true);
        NavidromeFixtureFactory::insertTrack($conn, 'mf-1', 'Track A', 'Old Artist', 240);
        NavidromeFixtureFactory::insertTrack($conn, 'mf-2', 'Track B', 'New Artist', 180);

        // Old Artist : already heard before 2024
        NavidromeFixtureFactory::insertScrobble($conn, 'user-1', 'mf-1', '2023-12-31 23:00:00');
        // 2024 plays
        for ($i = 0; $i < 3; $i++) {
            NavidromeFixtureFactory::insertScrobble($conn, 'user-1', 'mf-1', '2024-03-15 10:00:00');
        }
        for ($i = 0; $i < 5; $i++) {
            NavidromeFixtureFactory::insertScrobble($conn, 'user-1', 'mf-2', '2024-08-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . ' 12:00:00');
        }
        // Plays in another year (must NOT bleed into the 2024 wrapped)
        NavidromeFixtureFactory::insertScrobble($conn, 'user-1', 'mf-2', '2025-01-15 12:00:00');

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });
        $em->method('flush');

        $repo = $this->createMock(StatsSnapshotRepository::class);
        $repo->method('findOneByPeriod')->willReturn(null);

        $navidrome = new NavidromeRepository($this->dbPath, 'admin');
        $service = new WrappedService($navidrome, $repo, $em);

        $snapshot = $service->compute(2024);
        $this->assertSame('wrapped-2024', $snapshot->getPeriod());
        $data = $snapshot->getData();

        $this->assertSame(8, $data['total_plays'], '3 + 5 in 2024, none from 2023 / 2025');
        $this->assertSame(2, $data['distinct_tracks']);
        $this->assertSame(2024, $data['wrapped_year']);

        // Only "New Artist" had its first scrobble in 2024 — Old Artist already
        // existed in 2023.
        $this->assertCount(1, $data['wrapped_new_artists']);
        $this->assertSame('New Artist', $data['wrapped_new_artists'][0]['artist']);

        $this->assertSame(['month' => '2024-08', 'plays' => 5], $data['wrapped_most_active_month']);
        $this->assertSame(5, $data['wrapped_streak_days']);
        $this->assertGreaterThan(0, $data['wrapped_total_seconds_estimate']);
    }
}
