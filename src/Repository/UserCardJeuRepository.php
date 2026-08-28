<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserCardJeu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserCardJeu>
 */
class UserCardJeuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCardJeu::class);
    }

    /**
     * @return UserCardJeu[]
     */
    public function findByUserSorted(User $user): array
    {
        return $this->createQueryBuilder('ucj')
            ->join('ucj.cardJeu', 'cj')
            ->join('cj.rarity', 'r')
            ->andWhere('ucj.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('cj.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
