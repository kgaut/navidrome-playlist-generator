<?php

namespace App\Tests\Generator;

use App\Generator\TopLastDaysGenerator;
use App\Navidrome\NavidromeRepository;
use App\Tests\Navidrome\NavidromeFixtureFactory;
use PHPUnit\Framework\TestCase;

class TopLastDaysGeneratorTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/navidrome-gen-' . uniqid() . '.db';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testGenerateReturnsTopTracks(): void
    {
        $conn = NavidromeFixtureFactory::createDatabase($this->dbPath, withScrobbles: true);

        NavidromeFixtureFactory::insertTrack($conn, 'mf-a', 'A');
        NavidromeFixtureFactory::insertTrack($conn, 'mf-b', 'B');

        // 3 plays of A in last week
        for ($i = 0; $i < 3; $i++) {
            NavidromeFixtureFactory::insertScrobble($conn, 'user-1', 'mf-a', date('Y-m-d H:i:s', strtotime("-{$i} day")));
        }
        // 1 play of B
        NavidromeFixtureFactory::insertScrobble($conn, 'user-1', 'mf-b', date('Y-m-d H:i:s', strtotime('-2 day')));

        $repo = new NavidromeRepository($this->dbPath, 'admin');
        $generator = new TopLastDaysGenerator($repo);

        $this->assertSame('top-last-days', $generator->getKey());
        $this->assertNotEmpty($generator->getParameterSchema());

        $result = $generator->generate(['days' => 7], 10);
        $this->assertSame(['mf-a', 'mf-b'], $result);
    }

    public function testRespectsLimit(): void
    {
        $conn = NavidromeFixtureFactory::createDatabase($this->dbPath, withScrobbles: true);

        for ($i = 1; $i <= 5; $i++) {
            NavidromeFixtureFactory::insertTrack($conn, "mf-$i", "T$i");
            for ($j = 0; $j < (10 - $i); $j++) {
                // -1d offset so all scrobbles fall strictly before $now used in the generator.
                NavidromeFixtureFactory::insertScrobble($conn, 'user-1', "mf-$i", date('Y-m-d H:i:s', time() - 86400));
            }
        }

        $repo = new NavidromeRepository($this->dbPath, 'admin');
        $result = (new TopLastDaysGenerator($repo))->generate(['days' => 30], 3);

        $this->assertCount(3, $result);
        $this->assertSame(['mf-1', 'mf-2', 'mf-3'], $result);
    }
}
