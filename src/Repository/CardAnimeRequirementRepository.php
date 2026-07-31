<?php

namespace App\Repository;

use App\Entity\CardAnimeRequirement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardAnimeRequirement>
 */
class CardAnimeRequirementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardAnimeRequirement::class);
    }
}
