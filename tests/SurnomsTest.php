<?php

namespace App\Tests;

use App\Entity\Anime;
use App\Entity\Film;
use App\Entity\Jeu;
use App\Service\RechercheTitre;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Surnoms des œuvres (SurnomsTrait) et recherche par nom ou surnom (RechercheTitre).
 */
class SurnomsTest extends TestCase
{
    public function testLesSurnomsSontNettoyes(): void
    {
        $anime = (new Anime())->setSurnoms('  SNK ,Attack   on Titan,, snk , ');

        self::assertSame('SNK, Attack on Titan', $anime->getSurnoms());
        self::assertSame(['SNK', 'Attack on Titan'], $anime->getListeSurnoms());
    }

    public function testAucunSurnomDonneNull(): void
    {
        $anime = (new Anime())->setSurnoms(' , ');

        self::assertNull($anime->getSurnoms());
        self::assertSame([], $anime->getListeSurnoms());
        self::assertNull((new Anime())->setSurnoms(null)->getSurnoms());
    }

    public function testLesTroisFamillesOntDesSurnoms(): void
    {
        foreach ([new Anime(), new Film(), new Jeu()] as $titre) {
            $titre->setNom('Nom')->setSurnoms('Alias');
            self::assertSame('Nom Alias', $titre->getNomEtSurnoms());
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function recherches(): iterable
    {
        yield 'nom exact' => ['Shingeki no Kyojin', true];
        yield 'nom en minuscules' => ['shingeki', true];
        yield 'surnom en minuscules' => ['snk', true];
        yield 'surnom partiel' => ['attack on', true];
        yield 'recherche vide' => ['  ', true];
        yield 'aucune correspondance' => ['naruto', false];
    }

    #[DataProvider('recherches')]
    public function testCorrespond(string $recherche, bool $attendu): void
    {
        self::assertSame($attendu, RechercheTitre::correspond($recherche, 'Shingeki no Kyojin', 'SNK, Attack on Titan'));
    }

    public function testSansSurnom(): void
    {
        self::assertTrue(RechercheTitre::correspond('one', 'One Piece', null));
        self::assertFalse(RechercheTitre::correspond('op', 'One Piece', null));
    }

    public function testMotifEnMinusculesEtJokersEchappes(): void
    {
        self::assertSame('%snk%', RechercheTitre::motif('  SNK '));
        self::assertSame('%100\\%\\_ok%', RechercheTitre::motif('100%_ok'));
    }

    public function testCondition(): void
    {
        self::assertSame(
            '(LOWER(t.nom) LIKE :search OR LOWER(t.surnoms) LIKE :search)',
            RechercheTitre::condition('t', 'search'),
        );
    }
}
