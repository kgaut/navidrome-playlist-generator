<?php

namespace App\Tests\Service;

use App\Entity\LastFmHistoryEntry;
use App\LastFm\LastFmScrobble;
use App\Repository\LastFmHistoryEntryRepository;
use App\Service\LastFmHistoryService;
use App\Tests\LastFm\FakeLastFmClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class LastFmHistoryServiceTest extends TestCase
{
    public function testRefreshDeletesPreviousAndPersistsLimited(): void
    {
        $scrobbles = [];
        for ($i = 0; $i < 10; $i++) {
            $scrobbles[] = new LastFmScrobble(
                artist: 'Artist ' . $i,
                title: 'Title ' . $i,
                album: 'Album ' . $i,
                mbid: null,
                playedAt: new \DateTimeImmutable(sprintf('2026-01-%02d 12:00:00', $i + 1)),
            );
        }
        $client = new FakeLastFmClient($scrobbles);

        $callOrder = [];
        $persisted = [];

        $repo = $this->createMock(LastFmHistoryEntryRepository::class);
        $repo->expects($this->once())
            ->method('deleteForUser')
            ->with('me')
            ->willReturnCallback(function () use (&$callOrder): int {
                $callOrder[] = 'delete';
                return 0;
            });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $cb) => $cb($em));
        $em->method('persist')
            ->willReturnCallback(function (object $e) use (&$persisted, &$callOrder): void {
                $persisted[] = $e;
                $callOrder[] = 'persist';
            });
        $em->expects($this->once())->method('flush');

        $service = new LastFmHistoryService($client, $repo, $em);
        $count = $service->refresh('apikey', 'me', limit: 4);

        $this->assertSame(4, $count);
        $this->assertCount(4, $persisted);
        $this->assertSame('delete', $callOrder[0], 'deleteForUser must run before any persist()');
        /** @var LastFmHistoryEntry $first */
        $first = $persisted[0];
        $this->assertInstanceOf(LastFmHistoryEntry::class, $first);
        $this->assertSame('Artist 0', $first->getArtist());
        $this->assertSame('Title 0', $first->getTitle());
        $this->assertSame('me', $first->getLastfmUser());
    }
}
