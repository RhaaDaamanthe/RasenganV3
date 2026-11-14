<?php

namespace App\Service;

/**
 * Recherche d'une œuvre par son nom ou l'un de ses surnoms, insensible à la casse.
 *
 * Utilisé dans les QueryBuilder qui filtrent sur l'œuvre (catalogue, attribution,
 * gestion des joueurs, listes admin) :
 *
 *     $qb->andWhere('c.nom LIKE :search OR ' . RechercheTitre::condition('t', 'search'))
 *        ->setParameter('search', RechercheTitre::motif($search));
 */
final class RechercheTitre
{
    /**
     * Condition DQL : le nom ou les surnoms de l'œuvre `$alias` contiennent `:$parametre`.
     * Le paramètre doit être construit avec motif().
     */
    public static function condition(string $alias, string $parametre): string
    {
        return sprintf('(LOWER(%1$s.nom) LIKE :%2$s OR LOWER(%1$s.surnoms) LIKE :%2$s)', $alias, $parametre);
    }

    /**
     * Motif LIKE en minuscules (`%recherche%`), jokers saisis par l'utilisateur échappés.
     */
    public static function motif(string $recherche): string
    {
        return '%' . addcslashes(mb_strtolower(trim($recherche)), '%_\\') . '%';
    }

    /**
     * Même test côté PHP, pour les listes déjà chargées en mémoire (collection).
     */
    public static function correspond(string $recherche, ?string $nom, ?string $surnoms): bool
    {
        $recherche = mb_strtolower(trim($recherche));

        return $recherche === ''
            || str_contains(mb_strtolower((string) $nom), $recherche)
            || str_contains(mb_strtolower((string) $surnoms), $recherche);
    }
}
