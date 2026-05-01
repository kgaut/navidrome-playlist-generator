<?php

namespace App\Entity;

use App\Repository\StatsSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatsSnapshotRepository::class)]
#[ORM\Table(name: 'stats_snapshot')]
class StatsSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $period;

    /**
     * @var array{
     *     total_plays: int,
     *     distinct_tracks: int,
     *     top_artists: list<array{artist: string, plays: int}>,
     *     top_tracks: list<array{id: string, title: string, artist: string, album: string, plays: int}>,
     *     window_from: ?string,
     *     window_to: ?string
     * }
     */
    #[ORM\Column(type: Types::JSON)]
    private array $data = [
        'total_plays' => 0,
        'distinct_tracks' => 0,
        'top_artists' => [],
        'top_tracks' => [],
        'window_from' => null,
        'window_to' => null,
    ];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $computedAt;

    public function __construct(string $period)
    {
        $this->period = $period;
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    /**
     * @return array{
     *     total_plays: int,
     *     distinct_tracks: int,
     *     top_artists: list<array{artist: string, plays: int}>,
     *     top_tracks: list<array{id: string, title: string, artist: string, album: string, plays: int}>,
     *     window_from: ?string,
     *     window_to: ?string
     * }
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array{
     *     total_plays: int,
     *     distinct_tracks: int,
     *     top_artists: list<array{artist: string, plays: int}>,
     *     top_tracks: list<array{id: string, title: string, artist: string, album: string, plays: int}>,
     *     window_from: ?string,
     *     window_to: ?string
     * } $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        $this->computedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getComputedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }
}
