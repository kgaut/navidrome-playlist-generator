<?php

namespace App\Tests\Service;

use App\Entity\RunHistory;
use App\Service\RunHistoryRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RunHistoryRecorderTest extends TestCase
{
    public function testSuccessfulRunPersistsMetricsAndDuration(): void
    {
        $em = $this->makeFakeEntityManager();
        $recorder = new RunHistoryRecorder($em['em']);

        $result = $recorder->record(
            type: RunHistory::TYPE_PLAYLIST,
            reference: '42',
            label: 'Test playlist',
            action: fn () => ['ok' => true, 'count' => 7],
            extractMetrics: static fn (array $r) => ['tracks' => $r['count']],
        );

        $this->assertSame(['ok' => true, 'count' => 7], $result);
        $this->assertCount(1, $em['persisted']);
        /** @var RunHistory $entry */
        $entry = $em['persisted'][0];
        $this->assertSame(RunHistory::STATUS_SUCCESS, $entry->getStatus());
        $this->assertSame(['tracks' => 7], $entry->getMetrics());
        $this->assertNotNull($entry->getFinishedAt());
        $this->assertNotNull($entry->getDurationMs());
    }

    public function testFailingRunRecordsErrorAndRethrows(): void
    {
        $em = $this->makeFakeEntityManager();
        $recorder = new RunHistoryRecorder($em['em']);

        $thrownMessage = null;
        try {
            $recorder->record(
                type: RunHistory::TYPE_LASTFM_IMPORT,
                reference: 'me',
                label: 'Test import',
                action: function (): never {
                    throw new \RuntimeException('boom');
                },
            );
        } catch (\Throwable $e) {
            $thrownMessage = $e->getMessage();
        }

        $this->assertSame('boom', $thrownMessage);

        /** @var RunHistory $entry */
        $entry = $em['persisted'][0];
        $this->assertSame(RunHistory::STATUS_ERROR, $entry->getStatus());
        $this->assertSame('boom', $entry->getMessage());
        $this->assertNotNull($entry->getDurationMs());
    }

    /**
     * @return array{em: EntityManagerInterface, persisted: list<RunHistory>}
     */
    private function makeFakeEntityManager(): array
    {
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush');

        return ['em' => $em, 'persisted' => &$persisted];
    }
}
