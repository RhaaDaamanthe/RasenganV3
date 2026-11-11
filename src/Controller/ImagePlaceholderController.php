<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dev uniquement : les images de cartes (public/images/Cartes/) ne sont pas versionnées,
 * un poste local n'en a donc qu'une partie. Quand une image demandée n'existe pas, le
 * serveur web retombe sur index.php (.htaccess) et cette route renvoie une carte de
 * remplacement au lieu d'une image cassée.
 *
 * La route n'existe pas en prod (`env: 'dev'`).
 */
final class ImagePlaceholderController extends AbstractController
{
    /** Couleur de la carte de remplacement selon le dossier de rareté. */
    private const COULEURS = [
        'Communes' => '#8a8f98',
        'Rares' => '#3b82f6',
        'Épiques' => '#a855f7',
        'Legendaires' => '#f59e0b',
        'Mythiques' => '#ef4444',
        'Events' => '#10b981',
    ];

    #[Route('/images/Cartes/{chemin}', name: 'app_dev_image_carte', requirements: ['chemin' => '.+'], env: 'dev', priority: 100)]
    public function carte(string $chemin): Response
    {
        $fichier = $this->getParameter('app.public_dir') . '/images/Cartes/' . $chemin;

        // Serveur sans réécriture conditionnelle (ex. `php -S ... public/index.php`) :
        // toutes les requêtes passent ici, y compris celles d'images présentes.
        if (!str_contains($chemin, '..') && is_file($fichier)) {
            return new BinaryFileResponse($fichier);
        }

        $dossier = explode('/', $chemin)[0];
        $rarete = preg_replace('/^(Films|Jeux)_/', '', $dossier);
        $couleur = self::COULEURS[$rarete] ?? '#6b7280';

        // "12_Rock Lee.webp" -> "Rock Lee"
        $nom = pathinfo($chemin, PATHINFO_FILENAME);
        $nom = preg_replace('/^\d+_/', '', $nom);
        $nom = htmlspecialchars($nom, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $legende = htmlspecialchars($dossier, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="726" height="1000" viewBox="0 0 726 1000">
              <rect x="8" y="8" width="710" height="984" rx="36" fill="#1f2430" stroke="{$couleur}" stroke-width="16"/>
              <text x="363" y="470" text-anchor="middle" font-family="sans-serif" font-size="56" font-weight="bold" fill="#ffffff">{$nom}</text>
              <text x="363" y="550" text-anchor="middle" font-family="sans-serif" font-size="36" fill="{$couleur}">{$legende}</text>
              <text x="363" y="930" text-anchor="middle" font-family="sans-serif" font-size="28" fill="#9ca3af">image absente en local</text>
            </svg>
            SVG;

        return new Response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store',
        ]);
    }
}
