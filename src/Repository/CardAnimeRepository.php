<?php

namespace App\Repository;

use App\Entity\CardAnime;
use App\Entity\Anime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardAnime>
 */
class CardAnimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardAnime::class);
    }

    /**
     * @return CardAnime[]
     */
    public function findByAnimeSorted(Anime $anime): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.anime = :anime')
            ->setParameter('anime', $anime)
            ->orderBy('c.rarity', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}