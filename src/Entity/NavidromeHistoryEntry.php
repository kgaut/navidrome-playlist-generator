<?php

namespace App\Entity;

use App\Repository\NavidromeHistoryEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cached snapshot of one of the most recent scrobbles fetched from the
 * Navidrome `scrobbles` table. Kept in the tool's own SQLite DB so the
 * page renders without re-running the SQL on every request, with the
 * same wipe + re-insert refresh model as the Last.fm history cache.
 */
#[ORM\Entity(repositoryClass: NavidromeHistoryEntryRepository::class)]
#[ORM\Table(name: 'navidrome_history')]
#[ORM\Index(columns: ['played_at'], name: 'idx_navidrome_history_played')]
class NavidromeHistoryEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $mediaFileId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $playedAt;

    #[ORM\Column(length: 255)]
    private string $artist;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $album = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        string $mediaFileId,
        \DateTimeImmutable $playedAt,
        string $artist,
        string $title,
        ?string $album = null,
    ) {
        $this->mediaFileId = $mediaFileId;
        $this->playedAt = $playedAt;
        $this->artist = $artist;
        $this->title = $title;
        $this->album = $album !== null && $album !== '' ? $album : null;
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMediaFileId(): string
    {
        return $this->mediaFileId;
    }

    public function getPlayedAt(): \DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function getArtist(): string
    {
        return $this->artist;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
