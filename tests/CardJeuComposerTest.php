<?php

namespace App\Tests;

use App\Entity\Rarities;
use App\Service\Cadrage;
use App\Service\CardJeuComposer;
use PHPUnit\Framework\TestCase;

/**
 * Éditeur de cartes jeu vidéo : bornes du cadrage et assemblage réel avec le cadre et la
 * police installés dans public/ (GD requis).
 */
class CardJeuComposerTest extends TestCase
{
    private const F = CardJeuComposer::FENETRE;

    private CardJeuComposer $composer;
    private ?string $source = null;

    protected function setUp(): void
    {
        $this->composer = new CardJeuComposer(dirname(__DIR__) . '/public');
    }

    protected function tearDown(): void
    {
        if ($this->source !== null && is_file($this->source)) {
            unlink($this->source);
        }
    }

    public function testCadrageParDefautRemplitLaFenetreEtCaleEnHaut(): void
    {
        // Image très large : c'est la hauteur qui impose l'échelle, l'image est centrée.
        $c = CardJeuComposer::cadrageParDefaut(4000, 1610);

        self::assertEqualsWithDelta(1.0, $c->scale, 1e-9);
        self::assertSame((float) self::F['y'], (float) $c->y);
        self::assertEqualsWithDelta(self::F['x'] + (self::F['w'] - 4000) / 2, $c->x, 1e-9);
    }

    public function testBornerImposeLeZoomMinimumEtMaximum(): void
    {
        $min = CardJeuComposer::echelleMin(1345, 1610);
        self::assertEqualsWithDelta(1.0, $min, 1e-9);

        self::assertEqualsWithDelta(1.0, CardJeuComposer::borner(new Cadrage(0, 0, 0.2), 1345, 1610)->scale, 1e-9);
        self::assertEqualsWithDelta(
            CardJeuComposer::ZOOM_MAX,
            CardJeuComposer::borner(new Cadrage(0, 0, 50), 1345, 1610)->scale,
            1e-9,
        );
    }

    public function testBornerNeLaissePasDeVideDansLaFenetre(): void
    {
        // Image deux fois plus grande que la fenêtre, poussée trop loin dans les deux sens.
        $trop = CardJeuComposer::borner(new Cadrage(5000, 5000, 2), 1345, 1610);
        self::assertSame((float) self::F['x'], $trop->x);
        self::assertSame((float) self::F['y'], $trop->y);

        $pasAssez = CardJeuComposer::borner(new Cadrage(-9000, -9000, 2), 1345, 1610);
        self::assertSame((float) (self::F['x'] + self::F['w'] - 2690), $pasAssez->x);
        self::assertSame((float) (self::F['y'] + self::F['h'] - 3220), $pasAssez->y);
    }

    public function testCadrageDepuisSaisieRefuseLesValeursNonNumeriques(): void
    {
        self::assertNull(Cadrage::depuisSaisie('12', '', '1.5'));
        self::assertNull(Cadrage::depuisSaisie('abc', '1', '1'));

        $c = Cadrage::depuisSaisie('12.5', '-3', '1.25');
        self::assertSame([12.5, -3.0, 1.25], [$c->x, $c->y, $c->scale]);
    }

    public function testNomFichierSansAccentsNiCaracteresSpeciaux(): void
    {
        self::assertSame('Sophie_12.webp', CardJeuComposer::nomFichier('Sophie', 12));
        self::assertSame('Gustave_l_Ecrivain_3.webp', CardJeuComposer::nomFichier("Gustave l'Écrivain", 3));
        self::assertSame('carte_7.webp', CardJeuComposer::nomFichier('???', 7));
    }

    public function testSeulesLesRaretesAvecCadreSontDisponibles(): void
    {
        self::assertContains(Rarities::COMMUNE, $this->composer->raretesDisponibles());
        self::assertNull($this->composer->cadrePourRarete($this->rarete(999, 'Inconnue')));
    }

    public function testToutesLesRaretesUtilisentLeStyleDeTexteAdapte(): void
    {
        $configuration = $this->composer->configurationEditeur();
        $attendues = [
            Rarities::COMMUNE => [[245, 247, 240], [19, 52, 16], [56, 150, 43]],
            Rarities::RARE => [[245, 247, 240], [5, 24, 40], [0, 145, 235]],
            Rarities::EPIQUE => [[245, 247, 240], [29, 8, 43], [177, 71, 235]],
            Rarities::LEGENDAIRE => [[255, 250, 238], [48, 25, 0], [230, 128, 0]],
            Rarities::MYTHIQUE => [[255, 244, 244], [43, 5, 8], [220, 42, 52]],
            Rarities::EVENT => [[255, 244, 252], [40, 8, 30], [220, 45, 165]],
        ];

        foreach ($attendues as $id => [$remplissage, $halo, $contour]) {
            if (!isset($configuration['raretes'][$id])) {
                continue;
            }

            self::assertSame($remplissage, $configuration['raretes'][$id]['remplissage']);
            self::assertSame($halo, $configuration['raretes'][$id]['halo']);
            self::assertSame($contour, $configuration['raretes'][$id]['contour']);
        }
    }

    public function testComposerPlaceLePersoSousLeCadre(): void
    {
        // Image source unie, rouge : la fenêtre doit être rouge, le haut du cadre non.
        $source = imagecreatetruecolor(700, 800);
        imagefill($source, 0, 0, imagecolorallocate($source, 220, 20, 20));
        $this->source = tempnam(sys_get_temp_dir(), 'perso') . '.png';
        imagepng($source, $this->source);

        $carte = $this->composer->composer(
            $this->source,
            CardJeuComposer::cadrageParDefaut(700, 800),
            'Sophie',
            'Clair Obscur',
            12,
            $this->rarete(Rarities::COMMUNE, 'Communes'),
        );

        self::assertSame(CardJeuComposer::LARGEUR, imagesx($carte));
        self::assertSame(CardJeuComposer::HAUTEUR, imagesy($carte));

        $centre = imagecolorsforindex($carte, imagecolorat($carte, 760, 1000));
        self::assertSame([220, 20, 20], [$centre['red'], $centre['green'], $centre['blue']]);

        // Bord gauche du cadre, hors fenêtre : le perso ne doit pas y déborder.
        $bord = imagecolorsforindex($carte, imagecolorat($carte, 30, 1000));
        self::assertNotSame([220, 20, 20], [$bord['red'], $bord['green'], $bord['blue']]);
    }

    public function testComposerRefuseUneRareteSansCadre(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->composer->composer(__FILE__, new Cadrage(0, 0, 1), 'X', null, null, $this->rarete(999, 'Inconnue'));
    }

    private function rarete(int $id, string $libelle): Rarities
    {
        $rarete = (new Rarities())->setLibelle($libelle);
        (new \ReflectionProperty(Rarities::class, 'id'))->setValue($rarete, $id);

        return $rarete;
    }
}
