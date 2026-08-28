<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer toutes les cartes (anime + film + jeu vidéo)
        $animeCards = $entityManager->getRepository(CardAnime::class)->findAll();
        $filmCards  = $entityManager->getRepository(CardFilm::class)->findAll();
        $jeuCards   = $entityManager->getRepository(CardJeu::class)->findAll();

        $allCards = array_merge($animeCards, $filmCards, $jeuCards);

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
            'recentDrops' => $this->getRecentDrops($entityManager),
        ]);
    }

    /**
     * @return array<int, array{pseudo: string, nom: string, imagePath: ?string, rarite: string, type: string, relative: string}>
     */
    private function getRecentDrops(EntityManagerInterface $entityManager, int $limit = 8): array
    {
        $recentAnime = $entityManager->getRepository(UserCardAnime::class)
            ->createQueryBuilder('uca')
            ->where('uca.obtainedAt IS NOT NULL')
            ->orderBy('uca.obtainedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $recentFilm = $entityManager->getRepository(UserCardFilm::class)
            ->createQueryBuilder('ucf')
            ->where('ucf.obtainedAt IS NOT NULL')
            ->orderBy('ucf.obtainedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $recentJeu = $entityManager->getRepository(UserCardJeu::class)
            ->createQueryBuilder('ucj')
            ->where('ucj.obtainedAt IS NOT NULL')
            ->orderBy('ucj.obtainedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $drops = array_merge($recentAnime, $recentFilm, $recentJeu);

        usort($drops, fn ($a, $b) => $b->getObtainedAt() <=> $a->getObtainedAt());

        $drops = array_slice($drops, 0, $limit);

        return array_map(function (UserCardAnime|UserCardFilm|UserCardJeu $userCard) {
            $card = match (true) {
                $userCard instanceof UserCardAnime => $userCard->getCardAnime(),
                $userCard instanceof UserCardFilm => $userCard->getCardFilm(),
                default => $userCard->getCardJeu(),
            };

            return [
                'pseudo' => $userCard->getUser()?->getPseudo() ?? '?',
                'nom' => $card->getNom(),
                'imagePath' => $card->getImagePath(),
                'rarite' => $card->getRarity()?->getLibelle() ?? '',
                'type' => match (true) {
                    $userCard instanceof UserCardAnime => 'Anime',
                    $userCard instanceof UserCardFilm => 'Film',
                    default => 'Jeu vidéo',
                },
                'relative' => $this->formatRelativeTime($userCard->getObtainedAt()),
            ];
        }, $drops);
    }

    private function formatRelativeTime(\DateTimeImmutable $date): string
    {
        $diff = (new \DateTimeImmutable())->getTimestamp() - $date->getTimestamp();

        if ($diff < 60) {
            return 'à l\'instant';
        }
        if ($diff < 3600) {
            return 'il y a ' . (int) floor($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'il y a ' . (int) floor($diff / 3600) . 'h';
        }
        $days = (int) floor($diff / 86400);
        if ($days < 7) {
            return 'il y a ' . $days . 'j';
        }

        return $date->format('d/m/Y');
    }
}
