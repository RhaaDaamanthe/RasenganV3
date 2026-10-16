<?php

namespace App\Controller;

use App\Entity\Anime;
use App\Entity\Film;
use App\Entity\Jeu;
use App\Entity\Rarities;
use App\Entity\User;
use App\Enum\CardType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CatalogueController extends AbstractController
{
    private const CARDS_PER_PAGE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/catalogue', name: 'app_catalogue')]
    public function index(): Response
    {
        return $this->render('catalogue/index.html.twig', [
            'animes' => $this->getTitlesWithCardCount(CardType::Anime),
            'films' => $this->getTitlesWithCardCount(CardType::Film),
            'jeux' => $this->getTitlesWithCardCount(CardType::Jeu),
        ]);
    }

    #[Route('/catalogue/anime/{id}', name: 'app_catalogue_anime_cards')]
    public function showAnimeCards(Anime $anime, Request $request): Response
    {
        return $this->renderCards($request, CardType::Anime, $anime);
    }

    #[Route('/catalogue/anime', name: 'app_catalogue_all_anime_cards')]
    public function showAllAnimeCards(Request $request): Response
    {
        return $this->renderCards($request, CardType::Anime);
    }

    #[Route('/catalogue/film/{id}', name: 'app_catalogue_film_cards')]
    public function showFilmCards(Film $film, Request $request): Response
    {
        return $this->renderCards($request, CardType::Film, $film);
    }

    #[Route('/catalogue/film', name: 'app_catalogue_all_film_cards')]
    public function showAllFilmCards(Request $request): Response
    {
        return $this->renderCards($request, CardType::Film);
    }

    /**
     * Cartes d'un jeu vidéo précis.
     */
    #[Route('/catalogue/jeu/{id}', name: 'app_catalogue_jeu_cards')]
    public function showJeuCards(Jeu $jeu, Request $request): Response
    {
        return $this->renderCards($request, CardType::Jeu, $jeu);
    }

    #[Route('/catalogue/jeu', name: 'app_catalogue_all_jeu_cards')]
    public function showAllJeuCards(Request $request): Response
    {
        return $this->renderCards($request, CardType::Jeu);
    }

    /**
     * Œuvres ayant au moins une carte, avec leur nombre de cartes.
     *
     * @return array<int, array{id: int, nom: string, card_count: int}>
     */
    private function getTitlesWithCardCount(CardType $type): array
    {
        return $this->entityManager->getRepository($type->cardClass())
            ->createQueryBuilder('c')
            ->select('t.id, t.nom, count(c.id) as card_count')
            ->join('c.' . $type->titleProperty(), 't')
            ->groupBy('t.id, t.nom')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des cartes d'une famille, filtrable par nom et par rareté.
     *
     * Avec une œuvre ($title), seules ses cartes sont listées et la recherche porte sur
     * le nom de la carte ; sans œuvre, toutes les cartes de la famille sont listées et la
     * recherche porte aussi sur le nom de l'œuvre.
     */
    private function renderCards(Request $request, CardType $type, Anime|Film|Jeu|null $title = null): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        // Récupération des paramètres de recherche et filtrage
        $search = $request->query->get('search', '');
        $rarity = $request->query->get('rarity', '');

        $qb = $this->entityManager->getRepository($type->cardClass())
            ->createQueryBuilder('c')
            ->leftJoin('c.rarity', 'r');

        if ($title !== null) {
            $qb->where(sprintf('c.%s = :title', $type->titleProperty()))
               ->setParameter('title', $title);
        } else {
            $qb->leftJoin('c.' . $type->titleProperty(), 't');
        }

        if (!empty($search)) {
            $qb->andWhere($title !== null ? 'c.nom LIKE :search' : 'c.nom LIKE :search OR t.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if (!empty($rarity)) {
            $qb->andWhere('r.libelle = :rarity')
               ->setParameter('rarity', $rarity);
        }

        // Compte total pour la pagination
        $totalCards = (clone $qb)
            ->select('count(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $cards = $qb
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * self::CARDS_PER_PAGE)
            ->setMaxResults(self::CARDS_PER_PAGE)
            ->getQuery()
            ->getResult();

        $parameters = [
            'cards' => $cards,
            'currentPage' => $page,
            'totalPages' => ceil($totalCards / self::CARDS_PER_PAGE),
            'totalCards' => $totalCards,
            'search' => $search,
            'selectedRarity' => $rarity,
            'rarities' => $this->entityManager->getRepository(Rarities::class)->findAll(),
            'wishlistedIds' => $this->getWishlistedIds($type),
            'ownedIds' => $this->getOwnedIds($type),
        ];

        // Le template attend l'œuvre sous son propre nom : `anime`, `film` ou `jeu`.
        if ($title !== null) {
            $parameters = [$type->value => $title] + $parameters;
        }

        return $this->render(sprintf('catalogue/%sCards.html.twig', $type->value), $parameters);
    }

    /**
     * @return int[]
     */
    private function getWishlistedIds(CardType $type): array
    {
        $user = $this->getUser();

        return $user instanceof User
            ? array_map(fn ($card) => $card->getId(), $type->wishlistOf($user)->toArray())
            : [];
    }

    /**
     * @return int[]
     */
    private function getOwnedIds(CardType $type): array
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return [];
        }

        return array_map(
            fn ($userCard) => $type->cardOf($userCard)->getId(),
            array_filter($type->userCardsOf($user)->toArray(), fn ($userCard) => $userCard->getQuantity() > 0)
        );
    }
}
