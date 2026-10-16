<?php

namespace App\Tests;

use App\Entity\Rarities;
use App\Service\WheelOdds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WheelOddsTest extends TestCase
{
    private WheelOdds $odds;

    protected function setUp(): void
    {
        $this->odds = new WheelOdds();
    }

    public function testTablesSumTo100(): void
    {
        self::assertSame(100, array_sum(WheelOdds::RARITY_WEIGHTS));
        self::assertSame(100, array_sum(WheelOdds::RARITY_WEIGHTS_333));
    }

    public function testModeSelectsTable(): void
    {
        self::assertSame(WheelOdds::RARITY_WEIGHTS_333, $this->odds->weightsForMode('333'));
        self::assertSame(WheelOdds::RARITY_WEIGHTS, $this->odds->weightsForMode(null));
        self::assertSame(WheelOdds::RARITY_WEIGHTS, $this->odds->weightsForMode('nimporte'));
    }

    public function testNoMinimumKeepsBaseWeights(): void
    {
        self::assertSame(WheelOdds::RARITY_WEIGHTS, $this->odds->weightsForMinimumRarity(WheelOdds::RARITY_WEIGHTS, null));
    }

    public static function minimums(): iterable
    {
        foreach (['standard' => WheelOdds::RARITY_WEIGHTS, '333' => WheelOdds::RARITY_WEIGHTS_333] as $mode => $table) {
            yield "$mode, min rare" => [$table, Rarities::RARE];
            yield "$mode, min épique" => [$table, Rarities::EPIQUE];
        }
    }

    /**
     * Un plancher retire les raretés en dessous, garde le taux de Légendaire intact et
     * redistribue le reste : la table obtenue somme toujours à 100.
     */
    #[DataProvider('minimums')]
    public function testMinimumKeepsLegendaryRateAndSumsTo100(array $table, int $minRarityId): void
    {
        $weights = $this->odds->weightsForMinimumRarity($table, $minRarityId);

        self::assertSame($table[Rarities::LEGENDAIRE], $weights[Rarities::LEGENDAIRE]);
        self::assertEqualsWithDelta(100, array_sum($weights), 1e-9);
        foreach (array_keys($weights) as $id) {
            self::assertGreaterThanOrEqual($minRarityId, $id);
        }
    }

    public function testMinimumRareRedistributesProportionally(): void
    {
        $weights = $this->odds->weightsForMinimumRarity(WheelOdds::RARITY_WEIGHTS, Rarities::RARE);

        // 95 % à répartir entre Rare (35) et Épique (20), dans le même rapport.
        self::assertEqualsWithDelta(95 * 35 / 55, $weights[Rarities::RARE], 1e-9);
        self::assertEqualsWithDelta(95 * 20 / 55, $weights[Rarities::EPIQUE], 1e-9);
    }

    public function testMinimumLegendaryGivesLegendaryEveryTime(): void
    {
        self::assertSame(
            [Rarities::LEGENDAIRE => 100],
            $this->odds->weightsForMinimumRarity(WheelOdds::RARITY_WEIGHTS, Rarities::LEGENDAIRE),
        );
    }

    public function testMinimumWithoutLegendaryNormalisesTo100(): void
    {
        $weights = $this->odds->weightsForMinimumRarity([1 => 40, 2 => 35, 3 => 25], 2);

        self::assertEqualsWithDelta(100 * 35 / 60, $weights[2], 1e-9);
        self::assertEqualsWithDelta(100 * 25 / 60, $weights[3], 1e-9);
    }

    public function testPickUsesCumulativeThresholds(): void
    {
        $weights = WheelOdds::RARITY_WEIGHTS; // 40 / 35 / 20 / 5

        self::assertSame(Rarities::COMMUNE, $this->odds->pick($weights, 1));
        self::assertSame(Rarities::COMMUNE, $this->odds->pick($weights, 40));
        self::assertSame(Rarities::RARE, $this->odds->pick($weights, 41));
        self::assertSame(Rarities::RARE, $this->odds->pick($weights, 75));
        self::assertSame(Rarities::EPIQUE, $this->odds->pick($weights, 76));
        self::assertSame(Rarities::EPIQUE, $this->odds->pick($weights, 95));
        self::assertSame(Rarities::LEGENDAIRE, $this->odds->pick($weights, 96));
        self::assertSame(Rarities::LEGENDAIRE, $this->odds->pick($weights, 100));
    }

    public function testPickFallsBackToHighestRarityWhenWeightsSumBelow100(): void
    {
        self::assertSame(4, $this->odds->pick([1 => 50, 2 => 30, 4 => 19.9999999], 100));
    }

    public function testRollStaysInTable(): void
    {
        for ($i = 0; $i < 200; $i++) {
            self::assertArrayHasKey($this->odds->roll(WheelOdds::RARITY_WEIGHTS), WheelOdds::RARITY_WEIGHTS);
        }
    }
}
