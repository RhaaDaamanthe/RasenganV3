<?php

namespace App\Service;

/**
 * Placement de l'image du perso sur la carte, en pixels de la carte finale :
 * coin haut-gauche de l'image en (x, y), et facteur d'échelle appliqué à l'image source.
 */
final class Cadrage
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $scale,
    ) {
    }

    /**
     * Cadrage lu depuis le formulaire (champs cachés remplis par l'éditeur).
     * Retourne null si une valeur manque ou n'est pas un nombre.
     */
    public static function depuisSaisie(mixed $x, mixed $y, mixed $scale): ?self
    {
        foreach ([$x, $y, $scale] as $valeur) {
            if (!is_numeric($valeur)) {
                return null;
            }
        }

        return new self((float) $x, (float) $y, (float) $scale);
    }
}
