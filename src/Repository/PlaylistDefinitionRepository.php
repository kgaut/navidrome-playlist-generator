<?php

namespace App\Repository;

use App\Entity\PlaylistDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaylistDefinition>
 */
class PlaylistDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaylistDefinition::class);
    }

    /**
     * @return PlaylistDefinition[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PlaylistDefinition[]
     */
    public function findScheduledEnabled(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.enabled = true')
            ->andWhere('p.schedule IS NOT NULL')
            ->andWhere("p.schedule != ''")
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByName(string $name): ?PlaylistDefinition
    {
        return $this->findOneBy(['name' => $name]);
    }
}
