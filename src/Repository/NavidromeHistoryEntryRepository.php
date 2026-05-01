<?php

namespace App\Repository;

use App\Entity\NavidromeHistoryEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NavidromeHistoryEntry>
 */
class NavidromeHistoryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NavidromeHistoryEntry::class);
    }

    /**
     * @return NavidromeHistoryEntry[]
     */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.playedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLastFetchedAt(): ?\DateTimeImmutable
    {
        $row = $this->createQueryBuilder('h')
            ->select('MAX(h.fetchedAt) AS last_fetched')
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_array($row) || empty($row['last_fetched'])) {
            return null;
        }

        return new \DateTimeImmutable($row['last_fetched']);
    }

    public function deleteAll(): int
    {
        return (int) $this->createQueryBuilder('h')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
