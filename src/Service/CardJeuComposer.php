<?php

namespace App\Service;

use App\Entity\CardJeu;
use App\Entity\Rarities;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Génère l'image des cartes jeu vidéo à partir d'un cadre (un PNG par rareté, fenêtre
 * transparente), de l'image du perso et des textes (nom, jeu, numéro).
 *
 * La mise en page est décrite par les constantes ci-dessous ; `configurationEditeur()` les
 * transmet telles quelles à l'éditeur JS (`public/js/card_jeu_editor.js`), qui dessine son
 * aperçu avec les mêmes valeurs : l'aperçu et l'image générée correspondent.
 */
class CardJeuComposer
{
    /** Taille de la carte générée (celle des cadres). */
    public const LARGEUR = 1524;
    public const HAUTEUR = 2102;

    /**
     * Fenêtre où apparaît le perso. La transparence des cadres va de (97, 252) à (1426, 1848) ;
     * la fenêtre déborde un peu pour que le cadre recouvre les bords irréguliers.
     */
    public const FENETRE = ['x' => 90, 'y' => 245, 'w' => 1345, 'h' => 1610];

    /**
     * Emplacements des textes : taille (points GD), centre (cx, cy) et largeur max. Un texte
     * trop large est réduit par pas de 2 points jusqu'à tenir.
     */
    public const TEXTES = [
        'nom' => ['taille' => 72, 'cx' => 750, 'cy' => 108, 'largeurMax' => 900],
        'jeu' => ['taille' => 66, 'cx' => 665, 'cy' => 2015, 'largeurMax' => 820],
        'numero' => ['taille' => 64, 'cx' => 1310, 'cy' => 1910, 'largeurMax' => 160],
    ];

    /**
     * Halo des textes, du plus large au plus net : [rayon en px, opacité 0-1, couleur].
     * Chaque couche est le texte redessiné en cercle autour de sa position.
     */
    private const COUCHES_HALO = [
        [9, 0.09, 'halo'],
        [5, 0.25, 'halo'],
        [2, 0.80, 'contour'],
    ];

    /** Pas angulaire (en degrés) des couches de halo. */
    public const PAS_HALO = 20;

    /** Zoom maximal, relatif au zoom minimal qui remplit la fenêtre. */
    public const ZOOM_MAX = 4.0;

    /** Au-delà de cet agrandissement de l'image source, l'éditeur prévient qu'elle sera floue. */
    public const AGRANDISSEMENT_FLOU = 1.5;

    /** Nom de fichier du cadre (sans extension) et couleurs des textes, par rareté. */
    private const RARETES = [
        Rarities::COMMUNE => ['cadre' => 'commune', 'remplissage' => [205, 255, 200], 'halo' => [30, 200, 70], 'contour' => [10, 110, 35]],
        Rarities::RARE => ['cadre' => 'rare', 'remplissage' => [210, 232, 255], 'halo' => [40, 130, 230], 'contour' => [10, 50, 120]],
        Rarities::EPIQUE => ['cadre' => 'epique', 'remplissage' => [236, 214, 255], 'halo' => [160, 70, 230], 'contour' => [70, 20, 120]],
        Rarities::LEGENDAIRE => ['cadre' => 'legendaire', 'remplissage' => [255, 240, 200], 'halo' => [240, 170, 30], 'contour' => [120, 70, 0]],
        Rarities::MYTHIQUE => ['cadre' => 'mythique', 'remplissage' => [255, 212, 212], 'halo' => [230, 50, 50], 'contour' => [110, 10, 10]],
        Rarities::EVENT => ['cadre' => 'event', 'remplissage' => [255, 225, 245], 'halo' => [235, 80, 180], 'contour' => [110, 20, 80]],
    ];

    /** Dossiers et fichiers, relatifs au dossier public. */
    public const DOSSIER_CADRES = 'images/cadres/jeu';
    public const DOSSIER_SOURCES = 'images/sources_jeu';
    public const POLICE = 'fonts/OPTICopperplate.otf';

    public function __construct(
        #[Autowire('%app.public_dir%')] private readonly string $publicDir,
    ) {
    }

    // ------------------------------------------------------------------ Cadres et raretés

    /** Chemin public du cadre de la rareté, ou null si son PNG n'a pas encore été déposé. */
    public function cadrePourRarete(?Rarities $rarete): ?string
    {
        return $rarete?->getId() !== null ? $this->cadrePourId($rarete->getId()) : null;
    }

    /** @return int[] ids des raretés dont le cadre existe (les seules proposées dans l'éditeur) */
    public function raretesDisponibles(): array
    {
        return array_values(array_filter(
            array_keys(self::RARETES),
            fn (int $id) => $this->cadrePourId($id) !== null,
        ));
    }

    private function cadrePourId(int $rareteId): ?string
    {
        $nom = self::RARETES[$rareteId]['cadre'] ?? null;
        if ($nom === null) {
            return null;
        }
        $chemin = self::DOSSIER_CADRES . '/' . $nom . '.png';

        return is_file($this->publicDir . '/' . $chemin) ? $chemin : null;
    }

    /**
     * Mise en page transmise à l'éditeur JS. Les chemins (cadres, police) sont relatifs au
     * dossier public : le contrôleur les convertit en URL.
     */
    public function configurationEditeur(): array
    {
        $raretes = [];
        foreach ($this->raretesDisponibles() as $id) {
            $def = self::RARETES[$id];
            $raretes[$id] = [
                'cadre' => $this->cadrePourId($id),
                'remplissage' => $def['remplissage'],
                'halo' => $def['halo'],
                'contour' => $def['contour'],
            ];
        }

        return [
            'largeur' => self::LARGEUR,
            'hauteur' => self::HAUTEUR,
            'fenetre' => self::FENETRE,
            'textes' => self::TEXTES,
            'couchesHalo' => self::COUCHES_HALO,
            'pasHalo' => self::PAS_HALO,
            'zoomMax' => self::ZOOM_MAX,
            'agrandissementFlou' => self::AGRANDISSEMENT_FLOU,
            'police' => self::POLICE,
            'raretes' => $raretes,
        ];
    }

    // ------------------------------------------------------------------ Cadrage

    /** Plus petite échelle qui remplit entièrement la fenêtre (« cover »). */
    public static function echelleMin(int $largeurSource, int $hauteurSource): float
    {
        return max(self::FENETRE['w'] / $largeurSource, self::FENETRE['h'] / $hauteurSource);
    }

    /** Cadrage par défaut : fenêtre remplie, image centrée horizontalement et calée en haut. */
    public static function cadrageParDefaut(int $largeurSource, int $hauteurSource): Cadrage
    {
        $scale = self::echelleMin($largeurSource, $hauteurSource);
        $f = self::FENETRE;

        return new Cadrage($f['x'] + ($f['w'] - $largeurSource * $scale) / 2, $f['y'], $scale);
    }

    /**
     * Ramène un cadrage dans les limites : zoom entre le minimum et ZOOM_MAX fois ce minimum,
     * et image positionnée de façon à ne jamais laisser de vide dans la fenêtre.
     */
    public static function borner(Cadrage $cadrage, int $largeurSource, int $hauteurSource): Cadrage
    {
        $min = self::echelleMin($largeurSource, $hauteurSource);
        $scale = min(max($cadrage->scale, $min), $min * self::ZOOM_MAX);
        $f = self::FENETRE;
        $largeur = $largeurSource * $scale;
        $hauteur = $hauteurSource * $scale;

        $x = min(max($cadrage->x, $f['x'] + $f['w'] - $largeur), $f['x']);
        $y = min(max($cadrage->y, $f['y'] + $f['h'] - $hauteur), $f['y']);

        return new Cadrage($x, $y, $scale);
    }

    // ------------------------------------------------------------------ Composition

    /**
     * Assemble la carte. Le cadrage est borné ici aussi : on ne fait jamais confiance aux
     * valeurs reçues du navigateur.
     *
     * @throws \RuntimeException image source ou cadre illisible
     */
    public function composer(string $sourceAbsolue, Cadrage $cadrage, string $nom, ?string $jeu, ?int $numero, Rarities $rarete): \GdImage
    {
        $cadreChemin = $this->cadrePourRarete($rarete)
            ?? throw new \RuntimeException(sprintf('Aucun cadre pour la rareté « %s ».', $rarete->getLibelle()));
        $cadre = @imagecreatefrompng($this->publicDir . '/' . $cadreChemin)
            ?: throw new \RuntimeException('Cadre illisible : ' . $cadreChemin);
        $perso = $this->chargerImage($sourceAbsolue);

        $sw = imagesx($perso);
        $sh = imagesy($perso);
        $cadrage = self::borner($cadrage, $sw, $sh);

        $carte = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagealphablending($carte, false);
        imagefill($carte, 0, 0, imagecolorallocatealpha($carte, 0, 0, 0, 127));
        imagesavealpha($carte, true);
        imagealphablending($carte, true);

        // 1. Perso, limité à la fenêtre : on copie la partie de l'image qui tombe dedans.
        $f = self::FENETRE;
        imagecopyresampled(
            $carte, $perso,
            $f['x'], $f['y'],
            (int) round(($f['x'] - $cadrage->x) / $cadrage->scale),
            (int) round(($f['y'] - $cadrage->y) / $cadrage->scale),
            $f['w'], $f['h'],
            (int) round($f['w'] / $cadrage->scale),
            (int) round($f['h'] / $cadrage->scale),
        );

        // 2. Cadre par-dessus.
        imagecopy($carte, $cadre, 0, 0, 0, 0, self::LARGEUR, self::HAUTEUR);

        // 3. Textes.
        $couleurs = self::RARETES[$rarete->getId()];
        $this->ecrire($carte, $nom, self::TEXTES['nom'], $couleurs);
        if ($jeu !== null && $jeu !== '') {
            $this->ecrire($carte, $jeu, self::TEXTES['jeu'], $couleurs);
        }
        if ($numero !== null) {
            $this->ecrire($carte, (string) $numero, self::TEXTES['numero'], $couleurs);
        }

        return $carte;
    }

    /** Texte centré sur (cx, cy), réduit tant qu'il dépasse la largeur max, avec son halo. */
    private function ecrire(\GdImage $carte, string $texte, array $zone, array $couleurs): void
    {
        $police = $this->publicDir . '/' . self::POLICE;
        $taille = $zone['taille'];
        do {
            $b = imagettfbbox($taille, 0, $police, $texte);
            $largeur = $b[2] - $b[0];
        } while ($largeur > $zone['largeurMax'] && ($taille -= 2) > 10);

        $x = (int) round($zone['cx'] - $largeur / 2 - $b[0]);
        $y = (int) round($zone['cy'] - ($b[1] + $b[7]) / 2);

        foreach (self::COUCHES_HALO as [$rayon, $opacite, $cle]) {
            [$r, $g, $bl] = $couleurs[$cle];
            $couleur = imagecolorallocatealpha($carte, $r, $g, $bl, (int) round(127 * (1 - $opacite)));
            for ($angle = 0; $angle < 360; $angle += self::PAS_HALO) {
                $dx = (int) round($rayon * cos(deg2rad($angle)));
                $dy = (int) round($rayon * sin(deg2rad($angle)));
                imagettftext($carte, $taille, 0, $x + $dx, $y + $dy, $couleur, $police, $texte);
            }
        }
        [$r, $g, $bl] = $couleurs['remplissage'];
        imagettftext($carte, $taille, 0, $x, $y, imagecolorallocate($carte, $r, $g, $bl), $police, $texte);
    }

    private function chargerImage(string $chemin): \GdImage
    {
        $contenu = is_file($chemin) ? file_get_contents($chemin) : false;
        $image = $contenu !== false ? @imagecreatefromstring($contenu) : false;
        if (!$image) {
            throw new \RuntimeException('Image du perso illisible : ' . basename($chemin));
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    // ------------------------------------------------------------------ Fichiers

    /**
     * Range l'image du perso téléversée dans le dossier des sources.
     *
     * @return array{chemin: string, largeur: int, hauteur: int} chemin public et dimensions
     *
     * @throws \RuntimeException fichier qui n'est pas une image
     */
    public function enregistrerSource(UploadedFile $fichier): array
    {
        $dimensions = @getimagesize($fichier->getPathname());
        if ($dimensions === false) {
            throw new \RuntimeException('Le fichier envoyé n\'est pas une image lisible.');
        }

        $extension = $fichier->guessExtension() ?: 'png';
        $nom = bin2hex(random_bytes(8)) . '.' . $extension;
        $fichier->move($this->dossier(self::DOSSIER_SOURCES), $nom);

        return ['chemin' => self::DOSSIER_SOURCES . '/' . $nom, 'largeur' => $dimensions[0], 'hauteur' => $dimensions[1]];
    }

    /** @return array{0: int, 1: int} dimensions d'une image source déjà enregistrée */
    public function dimensionsSource(string $cheminPublic): array
    {
        $dimensions = @getimagesize($this->publicDir . '/' . $cheminPublic);
        if ($dimensions === false) {
            throw new \RuntimeException('Image source introuvable : ' . $cheminPublic);
        }

        return [$dimensions[0], $dimensions[1]];
    }

    /**
     * Génère (ou régénère) l'image d'une carte composée et met à jour son `imagePath`.
     * La carte doit déjà avoir un id (il entre dans le nom du fichier). L'ancienne image
     * générée est supprimée si son chemin change (renommage, changement de rareté).
     */
    public function generer(CardJeu $carte): string
    {
        $composition = $carte->getComposition()
            ?? throw new \LogicException('Cette carte n\'a pas de composition.');
        $rarete = $carte->getRarity()
            ?? throw new \RuntimeException('La rareté de la carte est obligatoire.');
        if ($carte->getId() === null) {
            throw new \LogicException('La carte doit être enregistrée avant de générer son image.');
        }

        $image = $this->composer(
            $this->publicDir . '/' . $composition['source'],
            new Cadrage($composition['x'], $composition['y'], $composition['scale']),
            (string) $carte->getNom(),
            $carte->getJeu()?->getNom(),
            $carte->getNumero(),
            $rarete,
        );

        $dossier = 'images/Cartes/Jeux_' . $rarete->getLibelle();
        $chemin = $dossier . '/' . self::nomFichier((string) $carte->getNom(), $carte->getId());
        $destination = $this->dossier($dossier) . '/' . basename($chemin);

        if (!imagewebp($image, $destination, 90)) {
            throw new \RuntimeException('Impossible d\'écrire l\'image de la carte.');
        }

        $ancien = $carte->getImagePath();
        if ($ancien !== null && $ancien !== $chemin) {
            $this->supprimer($ancien);
        }
        $carte->setImagePath($chemin);

        return $chemin;
    }

    /** Supprime un fichier du dossier public (s'il existe). */
    public function supprimer(?string $cheminPublic): void
    {
        if ($cheminPublic === null || $cheminPublic === '' || str_contains($cheminPublic, '..')) {
            return;
        }
        $absolu = $this->publicDir . '/' . $cheminPublic;
        if (is_file($absolu)) {
            unlink($absolu);
        }
    }

    /** « Sophie Durand » + 12 → « Sophie_Durand_12.webp » (sans accents ni caractères spéciaux). */
    public static function nomFichier(string $nom, int $id): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $nom);
        $propre = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $ascii !== false ? $ascii : $nom), '_');

        return ($propre !== '' ? $propre : 'carte') . '_' . $id . '.webp';
    }

    private function dossier(string $cheminPublic): string
    {
        $absolu = $this->publicDir . '/' . $cheminPublic;
        if (!is_dir($absolu) && !mkdir($absolu, 0777, true) && !is_dir($absolu)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier « %s ».', $cheminPublic));
        }

        return $absolu;
    }
}
