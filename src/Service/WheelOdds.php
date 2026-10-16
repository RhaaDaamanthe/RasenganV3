<?php

namespace App\Service;

use App\Entity\Rarities;

/**
 * Probabilités de la roue : tables de poids (en %) par rareté et tirage pondéré.
 *
 * Les mythiques et les events ne se tirent pas : ils n'apparaissent dans aucune table.
 */
class WheelOdds
{
    public const RARITY_WEIGHTS = [
        Rarities::COMMUNE => 40,
        Rarities::RARE => 35,
        Rarities::EPIQUE => 20,
        Rarities::LEGENDAIRE => 5,
    ];

    public const RARITY_WEIGHTS_333 = [
        Rarities::COMMUNE => 33,
        Rarities::RARE => 33,
        Rarities::EPIQUE => 33,
        Rarities::LEGENDAIRE => 1,
    ];

    /**
     * @return array<int, int> table du mode demandé ('333' ou standard)
     */
    public function weightsForMode(?string $mode): array
    {
        return $mode === '333' ? self::RARITY_WEIGHTS_333 : self::RARITY_WEIGHTS;
    }

    /**
     * Applique un plancher de rareté à une table de poids de base. Le taux de
     * Légendaire ne bouge jamais ; seules les raretés en dessous se
     * redistribuent le reste proportionnellement entre elles.
     *
     * @param array<int, int> $baseWeights
     *
     * @return array<int, float|int>
     */
    public function weightsForMinimumRarity(array $baseWeights, ?int $minRarityId): array
    {
        if ($minRarityId === null) {
            return $baseWeights;
        }

        $filtered = array_filter(
            $baseWeights,
            fn (int $id) => $id >= $minRarityId,
            ARRAY_FILTER_USE_KEY
        );

        if ($filtered === []) {
            return $baseWeights;
        }

        if (count($filtered) === 1) {
            return [array_key_first($filtered) => 100];
        }

        $legendaryId = Rarities::LEGENDAIRE;
        if (array_key_exists($legendaryId, $filtered)) {
            $legendaryWeight = $baseWeights[$legendaryId];
            $others = array_diff_key($filtered, [$legendaryId => true]);
            $othersTotal = array_sum($others);
            $remainingBudget = 100 - $legendaryWeight;

            $result = $othersTotal > 0
                ? array_map(fn (int $weight) => $weight / $othersTotal * $remainingBudget, $others)
                : [];
            $result[$legendaryId] = $legendaryWeight;
            ksort($result);

            return $result;
        }

        $total = array_sum($filtered);

        return array_map(fn (int $weight) => $weight / $total * 100, $filtered);
    }

    /**
     * Choisit une rareté pour un jet de dé donné.
     *
     * @param array<int, float|int> $weights table dont la somme vaut 100
     * @param int                   $roll    jet entre 1 et 100
     */
    public function pick(array $weights, int $roll): int
    {
        $cumulative = 0;

        foreach ($weights as $id => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $id;
            }
        }

        // Par sécurité : les poids redistribués sont des flottants, et une table dont la
        // somme vaudrait 99,999… laisserait un jet de 100 hors des paliers. Il revient
        // alors au dernier palier (la rareté la plus haute), pas au premier.
        return array_key_last($weights);
    }

    /**
     * @param array<int, float|int> $weights
     */
    public function roll(array $weights): int
    {
        return $this->pick($weights, random_int(1, 100));
    }
}
