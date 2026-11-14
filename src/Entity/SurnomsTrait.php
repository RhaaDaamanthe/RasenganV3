<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Surnoms d'une œuvre (animé, film, jeu vidéo) : autres noms sous lesquels on peut la
 * retrouver dans les recherches (« SNK », « Attack on Titan » pour Shingeki no Kyojin).
 *
 * Stockés dans une colonne texte, séparés par des virgules, pour rester cherchables par
 * un simple LIKE (voir App\Service\RechercheTitre).
 */
trait SurnomsTrait
{
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $surnoms = null;

    /**
     * Surnoms tels qu'affichés dans le formulaire : « SNK, Attack on Titan ».
     */
    public function getSurnoms(): ?string
    {
        return $this->surnoms;
    }

    /**
     * Accepte une liste séparée par des virgules ; espaces, doublons et entrées vides
     * sont nettoyés.
     */
    public function setSurnoms(?string $surnoms): static
    {
        $liste = self::decouperSurnoms($surnoms);
        $this->surnoms = $liste === [] ? null : implode(', ', $liste);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getListeSurnoms(): array
    {
        return self::decouperSurnoms($this->surnoms);
    }

    /**
     * Le nom et les surnoms, pour une recherche côté navigateur (attribut data-*).
     */
    public function getNomEtSurnoms(): string
    {
        return implode(' ', array_filter([$this->getNom(), $this->surnoms]));
    }

    /**
     * @return string[]
     */
    private static function decouperSurnoms(?string $surnoms): array
    {
        $liste = [];
        foreach (explode(',', (string) $surnoms) as $surnom) {
            $surnom = trim(preg_replace('/\s+/u', ' ', $surnom));
            if ($surnom !== '' && !in_array(mb_strtolower($surnom), array_map('mb_strtolower', $liste), true)) {
                $liste[] = $surnom;
            }
        }

        return $liste;
    }
}
