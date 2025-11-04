<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\Anime;
use App\Entity\Film;
use App\Entity\CardFilm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function showAnimeCards(Anime $anime, Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 80;
        $offset = ($page - 1) * $limit;

        // Récupération des paramètres de recherche et filtrage
        $search = $request->query->get('search', '');
        $rarity = $request->query->get('rarity', '');

        $repo = $entityManager->getRepository(CardAnime::class);
        $qb = $repo->createQueryBuilder('ca')
            ->leftJoin('ca.rarity', 'r')
            ->where('ca.anime = :anime')
            ->setParameter('anime', $anime);

        // Filtre de recherche (nom de la carte)
        if (!empty($search)) {
            $qb->andWhere('ca.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtre de rareté
        if (!empty($rarity)) {
            $qb->andWhere('r.libelle = :rarity')
               ->setParameter('rarity', $rarity);
        }

        // Compte total pour la pagination
        $totalCards = (clone $qb)
            ->select('count(ca.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupération des cartes avec pagination
        $cards = $qb
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('ca.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalCards / $limit);

        // Récupérer toutes les raretés pour le filtre
        $rarities = $entityManager->getRepository(\App\Entity\Rarities::class)->findAll();

        return $this->render('catalogue/animeCards.html.twig', [
            'anime' => $anime,
            'cards' => $cards,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'selectedRarity' => $rarity,
            'rarities' => $rarities,
        ]);
    }

    #[Route('/catalogue/anime', name: 'app_catalogue_all_anime_cards')]
    public function showAllAnimeCards(Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 80;
        $offset = ($page - 1) * $limit;

        // Récupération des paramètres de recherche et filtrage
        $search = $request->query->get('search', '');
        $rarity = $request->query->get('rarity', '');

        $repo = $entityManager->getRepository(CardAnime::class);
        $qb = $repo->createQueryBuilder('ca')
            ->leftJoin('ca.rarity', 'r')
            ->leftJoin('ca.anime', 'a');

        // Filtre de recherche (nom de la carte OU nom de l'animé)
        if (!empty($search)) {
            $qb->andWhere('ca.nom LIKE :search OR a.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtre de rareté
        if (!empty($rarity)) {
            $qb->andWhere('r.libelle = :rarity')
               ->setParameter('rarity', $rarity);
        }

        // Compte total pour la pagination
        $totalCards = (clone $qb)
            ->select('count(ca.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupération des cartes avec pagination
        $cards = $qb
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('ca.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalCards / $limit);

        // Récupérer toutes les raretés pour le filtre
        $rarities = $entityManager->getRepository(\App\Entity\Rarities::class)->findAll();

        return $this->render('catalogue/animeCards.html.twig', [
            'cards' => $cards,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'selectedRarity' => $rarity,
            'rarities' => $rarities,
        ]);
    }

    #[Route('/catalogue/film/{id}', name: 'app_catalogue_film_cards')]
    public function showFilmCards(Film $film, Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 80;
        $offset = ($page - 1) * $limit;

        // Récupération des paramètres de recherche et filtrage
        $search = $request->query->get('search', '');
        $rarity = $request->query->get('rarity', '');

        $repo = $entityManager->getRepository(CardFilm::class);
        $qb = $repo->createQueryBuilder('cf')
            ->leftJoin('cf.rarity', 'r')
            ->where('cf.film = :film')
            ->setParameter('film', $film);

        // Filtre de recherche (nom de la carte)
        if (!empty($search)) {
            $qb->andWhere('cf.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtre de rareté
        if (!empty($rarity)) {
            $qb->andWhere('r.libelle = :rarity')
               ->setParameter('rarity', $rarity);
        }

        // Compte total pour la pagination
        $totalCards = (clone $qb)
            ->select('count(cf.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupération des cartes avec pagination
        $cards = $qb
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('cf.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalCards / $limit);

        // Récupérer toutes les raretés pour le filtre
        $rarities = $entityManager->getRepository(\App\Entity\Rarities::class)->findAll();

        return $this->render('catalogue/filmCards.html.twig', [
            'film' => $film,
            'cards' => $cards,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'selectedRarity' => $rarity,
            'rarities' => $rarities,
        ]);
    }

    #[Route('/catalogue/film', name: 'app_catalogue_all_film_cards')]
    public function showAllFilmCards(Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 80;
        $offset = ($page - 1) * $limit;

        // Récupération des paramètres de recherche et filtrage
        $search = $request->query->get('search', '');
        $rarity = $request->query->get('rarity', '');

        $repo = $entityManager->getRepository(CardFilm::class);
        $qb = $repo->createQueryBuilder('cf')
            ->leftJoin('cf.rarity', 'r')
            ->leftJoin('cf.film', 'f');

        // Filtre de recherche (nom de la carte OU nom du film)
        if (!empty($search)) {
            $qb->andWhere('cf.nom LIKE :search OR f.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtre de rareté
        if (!empty($rarity)) {
            $qb->andWhere('r.libelle = :rarity')
               ->setParameter('rarity', $rarity);
        }

        // Compte total pour la pagination
        $totalCards = (clone $qb)
            ->select('count(cf.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupération des cartes avec pagination
        $cards = $qb
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('cf.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalCards / $limit);

        // Récupérer toutes les raretés pour le filtre
        $rarities = $entityManager->getRepository(\App\Entity\Rarities::class)->findAll();

        return $this->render('catalogue/filmCards.html.twig', [
            'cards' => $cards,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'selectedRarity' => $rarity,
            'rarities' => $rarities,
        ]);
    }
}