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
     * Prochain numéro libre de chaque jeu (plus grand numéro + 1), pour pré-remplir l'éditeur.
     * Les numéros ne sont jamais réattribués : un trou laissé par une suppression reste un trou.
     *
     * @return array<int, int> id du jeu => prochain numéro
     */
    public function findProchainsNumerosParJeu(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.jeu) AS jeuId', 'MAX(c.numero) AS maxNumero')
            ->where('c.jeu IS NOT NULL')
            ->groupBy('c.jeu')
            ->getQuery()
            ->getArrayResult();

        $numeros = [];
        foreach ($rows as $row) {
            $numeros[(int) $row['jeuId']] = (int) $row['maxNumero'] + 1;
        }

        return $numeros;
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
