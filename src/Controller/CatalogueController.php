<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\Anime;
use App\Entity\Film;
use App\Entity\CardFilm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogueController extends AbstractController
{
    #[Route('/catalogue', name: 'app_catalogue')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer les données pour le catalogue des animés
        $animes = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('ca')
            ->select('a.id, a.nom, count(ca.id) as card_count')
            ->join('ca.anime', 'a')
            ->groupBy('a.id, a.nom')
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();

        // Récupérer les données pour le catalogue des films
        $films = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('cf')
            ->select('f.id, f.nom, count(cf.id) as card_count')
            ->join('cf.film', 'f')
            ->groupBy('f.id, f.nom')
            ->orderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('catalogue/index.html.twig', [
            'animes' => $animes,
            'films' => $films,
        ]);
    }

    #[Route('/catalogue/anime/{id}', name: 'app_catalogue_anime_cards')]
    public function showAnimeCards(Anime $anime, EntityManagerInterface $entityManager): Response
    {
        // Récupère les cartes d'un animé spécifique et les trie par rareté décroissante puis par ID croissant
        $cards = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('ca')
            ->leftJoin('ca.rarity', 'r')
            ->where('ca.anime = :anime')
            ->setParameter('anime', $anime)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('ca.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('catalogue/animeCards.html.twig', [
            'anime' => $anime,
            'cards' => $cards,
        ]);
    }

    #[Route('/catalogue/anime', name: 'app_catalogue_all_anime_cards')]
    public function showAllAnimeCards(EntityManagerInterface $entityManager): Response
    {
        // Récupère toutes les cartes animés et les trie par rareté décroissante puis par ID croissant
        $cards = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('ca')
            ->leftJoin('ca.rarity', 'r')
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('ca.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('catalogue/animeCards.html.twig', [
            'cards' => $cards,
        ]);
    }

    #[Route('/catalogue/film/{id}', name: 'app_catalogue_film_cards')]
    public function showFilmCards(Film $film, EntityManagerInterface $entityManager): Response
    {
        // Récupère les cartes d'un film spécifique et les trie par rareté décroissante puis par ID croissant
        $cards = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('cf')
            ->leftJoin('cf.rarity', 'r')
            ->where('cf.film = :film')
            ->setParameter('film', $film)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('cf.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('catalogue/filmCards.html.twig', [
            'film' => $film,
            'cards' => $cards,
        ]);
    }

    #[Route('/catalogue/film', name: 'app_catalogue_all_film_cards')]
    public function showAllFilmCards(EntityManagerInterface $entityManager): Response
    {
        // Récupère toutes les cartes de films et les trie par rareté décroissante puis par ID croissant
        $cards = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('cf')
            ->leftJoin('cf.rarity', 'r')
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('cf.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('catalogue/filmCards.html.twig', [
            'cards' => $cards,
        ]);
    }
}