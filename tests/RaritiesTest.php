<?php

namespace App\Tests;

use App\Entity\Rarities;
use PHPUnit\Framework\TestCase;

class RaritiesTest extends TestCase
{
    private static function rarity(int $id, string $libelle): Rarities
    {
        $rarity = (new Rarities())->setLibelle($libelle);
        (new \ReflectionProperty(Rarities::class, 'id'))->setValue($rarity, $id);

        return $rarity;
    }

    public function testPointsFollowLibelle(): void
    {
        self::assertSame(1, self::rarity(Rarities::COMMUNE, 'Communes')->getPoints());
        self::assertSame(4, self::rarity(Rarities::LEGENDAIRE, 'Legendaires')->getPoints());
        self::assertSame(5, self::rarity(Rarities::MYTHIQUE, 'Mythiques')->getPoints());
        self::assertSame(6, self::rarity(Rarities::EVENT, 'Events')->getPoints());
        self::assertSame(0, self::rarity(99, 'Inconnue')->getPoints());
    }

    public function testMythiqueAndAuMoinsLegendaire(): void
    {
        self::assertTrue(self::rarity(Rarities::MYTHIQUE, 'Mythiques')->isMythique());
        self::assertFalse(self::rarity(Rarities::LEGENDAIRE, 'Legendaires')->isMythique());

        self::assertFalse(self::rarity(Rarities::EPIQUE, 'Épiques')->isAuMoinsLegendaire());
        foreach ([Rarities::LEGENDAIRE, Rarities::MYTHIQUE, Rarities::EVENT] as $id) {
            self::assertTrue(self::rarity($id, 'x')->isAuMoinsLegendaire());
        }
    }
}
