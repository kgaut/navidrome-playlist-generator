<?php

namespace App\Tests\Service;

use App\Entity\NavidromeHistoryEntry;
use App\Navidrome\NavidromeRepository;
use App\Repository\NavidromeHistoryEntryRepository;
use App\Service\NavidromeHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class NavidromeHistoryServiceTest extends TestCase
{
    public function testRefreshDeletesPreviousAndPersistsRows(): void
    {
        $rows = [];
        for ($i = 0; $i < 5; $i++) {
            $rows[] = [
                'media_file_id' => 'mf-' . $i,
                'played_at' => new \DateTimeImmutable(sprintf('2026-04-%02d 10:00:00', $i + 1)),
                'artist' => 'Artist ' . $i,
                'title' => 'Track ' . $i,
                'album' => $i % 2 === 0 ? 'Album ' . $i : '',
            ];
        }

        $navidrome = $this->createMock(NavidromeRepository::class);
        $navidrome->expects($this->once())
            ->method('getRecentScrobbles')
            ->with(100)
            ->willReturn($rows);

        $callOrder = [];
        $persisted = [];

        $repo = $this->createMock(NavidromeHistoryEntryRepository::class);
        $repo->expects($this->once())
            ->method('deleteAll')
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

        $service = new NavidromeHistoryService($navidrome, $repo, $em);
        $count = $service->refresh();

        $this->assertSame(5, $count);
        $this->assertCount(5, $persisted);
        $this->assertSame('delete', $callOrder[0], 'deleteAll must run before any persist()');
        /** @var NavidromeHistoryEntry $first */
        $first = $persisted[0];
        $this->assertInstanceOf(NavidromeHistoryEntry::class, $first);
        $this->assertSame('mf-0', $first->getMediaFileId());
        $this->assertSame('Artist 0', $first->getArtist());
        $this->assertSame('Track 0', $first->getTitle());
        $this->assertSame('Album 0', $first->getAlbum());
        // empty album coerced to null
        $this->assertNull($persisted[1]->getAlbum());
    }
}
