<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserCardFilm;
use App\Repository\UserRepository;
use App\Repository\UserCardAnimeRepository;
use App\Entity\Rarities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CollectionController extends AbstractController
{
    #[Route('/joueurs', name: 'app_players_list')]
    public function showPlayersList(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('collection/players_list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/collection/{id}', name: 'app_player_collection')]
    public function showPlayerCollection(
        User $user,
        UserCardAnimeRepository $userCardAnimeRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Récupère les filtres depuis l'URL
        $search = $request->query->get('search', '');
        $selectedRarity = $request->query->get('rarity', '');
        $selectedSection = $request->query->get('section', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 80;

        // Récupération des cartes de l'utilisateur
        $userCardAnimes = $userCardAnimeRepository->findByUserSorted($user);
        $userCardFilms = $entityManager->getRepository(UserCardFilm::class)
            ->createQueryBuilder('ucf')
            ->leftJoin('ucf.cardFilm', 'cf')
            ->leftJoin('cf.rarity', 'r')
            ->where('ucf.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('cf.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Fusion des deux collections
        $allUserCards = array_merge($userCardAnimes, $userCardFilms);

        // Filtrage des cartes
        $filteredCards = array_filter($allUserCards, function ($userCard) use ($search, $selectedRarity, $selectedSection) {
            // Détermine dynamiquement si c'est une carte Anime ou Film
            if (method_exists($userCard, 'getCardAnime') && $userCard->getCardAnime()) {
                $card = $userCard->getCardAnime();
                $type = 'anime';
            } elseif (method_exists($userCard, 'getCardFilm') && $userCard->getCardFilm()) {
                $card = $userCard->getCardFilm();
                $type = 'film';
            } else {
                return false;
            }

            // Filtre section (anime ou film)
            if ($selectedSection === 'anime' && $type !== 'anime') return false;
            if ($selectedSection === 'film' && $type !== 'film') return false;

            // Filtre rareté
            if ($selectedRarity && $card->getRarity() && $card->getRarity()->getLibelle() !== $selectedRarity) {
                return false;
            }

            // Filtre recherche texte
            if ($search) {
                $searchLower = mb_strtolower($search);
                $cardName = mb_strtolower($card->getNom());
                
                // Recherche dans le nom de la carte
                if (stripos($cardName, $searchLower) !== false) {
                    return true;
                }

                // Recherche dans le nom de l'animé
                if ($type === 'anime' && $card->getAnime()) {
                    $animeName = mb_strtolower($card->getAnime()->getNom());
                    if (stripos($animeName, $searchLower) !== false) {
                        return true;
                    }
                }

                // Recherche dans le nom du film
                if ($type === 'film' && $card->getFilm()) {
                    $filmName = mb_strtolower($card->getFilm()->getNom());
                    if (stripos($filmName, $searchLower) !== false) {
                        return true;
                    }
                }

                // Aucune correspondance trouvée
                return false;
            }

            return true;
        });

        // Pagination
        $totalCards = count($filteredCards);
        $totalPages = max(1, ceil($totalCards / $limit));
        $offset = ($page - 1) * $limit;
        $paginatedCards = array_slice($filteredCards, $offset, $limit);

        // Récupère toutes les raretés triées pour le menu déroulant
        $rarities = $entityManager->getRepository(Rarities::class)
            ->createQueryBuilder('r')
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('collection/player_collection.html.twig', [
            'user' => $user,
            'paginatedCards' => $paginatedCards,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'selectedRarity' => $selectedRarity,
            'selectedSection' => $selectedSection,
            'rarities' => $rarities,
        ]);
    }
}