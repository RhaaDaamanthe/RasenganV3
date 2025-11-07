<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer toutes les cartes (anime + film)
        $animeCards = $entityManager->getRepository(CardAnime::class)->findAll();
        $filmCards  = $entityManager->getRepository(CardFilm::class)->findAll();

        $allCards = array_merge($animeCards, $filmCards);

        // Construire un tableau d'images (chemins publics) et filtrer les entrées vides
        $allImagePaths = [];
        foreach ($allCards as $card) {
            $path = $card->getImagePath();
            if ($path && trim($path) !== '') {
                // Normaliser: si imagePath est déjà 'images/...' on garde tel quel
                $allImagePaths[] = $path;
            }
        }

        // Si peu d'images on prend ce qu'on a, sinon on shuffle et on prend n éléments
        shuffle($allImagePaths);

        // Nombre d'éléments initialement dans le track (5 visibles + quelques extras pour rotation)
        $initialCount = min(12, max(5, count($allImagePaths))); // au moins 5, au plus 12 ou moins si peu d'images

        $carouselImages = array_slice($allImagePaths, 0, $initialCount);

        return $this->render('home/index.html.twig', [
            'carouselImages' => $carouselImages,
            // Si tu veux un pool JS pour remplacement aléatoire côté client :
            'allCarouselImages' => $allImagePaths,
        ]);
    }
}
