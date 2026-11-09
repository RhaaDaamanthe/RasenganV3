<?php

namespace App\Repository;

use App\Entity\CardJeu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardJeu>
 */
class CardJeuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardJeu::class);
    }

    /**
     * Prochain numéro libre (plus grand numéro + 1), commun à tous les jeux, pour pré-remplir
     * l'éditeur. Les numéros ne sont jamais réattribués : un trou laissé par une suppression
     * reste un trou.
     */
    public function findProchainNumero(): int
    {
        $max = $this->createQueryBuilder('c')
            ->select('MAX(c.numero)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }

    /** @return CardJeu[] cartes dont l'image est générée par l'éditeur */
    public function findComposees(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.composition IS NOT NULL')
            ->getQuery()
            ->getResult();
    }
}
