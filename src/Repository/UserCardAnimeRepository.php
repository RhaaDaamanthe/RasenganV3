<?php

namespace App\Repository;

use App\Entity\UserCardAnime;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserCardAnime>
 */
class UserCardAnimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCardAnime::class);
    }

    /**
     * @return UserCardAnime[]
     */
    public function findByUserSorted(User $user): array
    {
        return $this->createQueryBuilder('uca')
            ->join('uca.cardAnime', 'ca')
            ->join('ca.rarity', 'r')
            ->andWhere('uca.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('ca.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}