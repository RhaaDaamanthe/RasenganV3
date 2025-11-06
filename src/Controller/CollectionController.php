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
use Symfony\Component\Routing\Annotation\Route;

class CollectionController extends AbstractController
{
    #[Route('/joueurs', name: 'app_players_list')]
    public function showPlayersList(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Récupère tous les utilisateurs
        $users = $userRepository->findAll();

        // Variables pour le total des cartes
        $playersWithPoints = [];
        $totalCards = 0;
        $totalAnimeCards = 0;
        $totalFilmCards = 0;

        foreach ($users as $user) {
            // Calcul des cartes anime de l'utilisateur
            $animeCount = $entityManager->getRepository(\App\Entity\UserCardAnime::class)
                ->createQueryBuilder('uca')
                ->select('SUM(uca.quantity)')
                ->where('uca.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            // Calcul des cartes film de l'utilisateur
            $filmCount = $entityManager->getRepository(\App\Entity\UserCardFilm::class)
                ->createQueryBuilder('ucf')
                ->select('SUM(ucf.quantity)')
                ->where('ucf.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            // Calcul du total des points
            $totalPoints = (int) $animeCount + (int) $filmCount;

            // Ajouter l'utilisateur et ses points à la liste
            $playersWithPoints[] = [
                'user' => $user,
                'points' => $totalPoints,
            ];

            // Mettre à jour les totaux
            $totalAnimeCards += (int) $animeCount;
            $totalFilmCards += (int) $filmCount;
            $totalCards += (int) $animeCount + (int) $filmCount;
        }

        // Trier les utilisateurs par points, du plus grand au plus petit
        usort($playersWithPoints, function ($a, $b) {
            return $b['points'] - $a['points'];
        });

        // Extraire la liste triée des utilisateurs
        $sortedUsers = array_map(function ($player) {
            return $player['user'];
        }, $playersWithPoints);

        // Rendre la vue avec les données
        return $this->render('collection/players_list.html.twig', [
            'users' => $sortedUsers,
            'totalCards' => $totalCards,      // Total général des cartes
            'animeCount' => $totalAnimeCards, // Total des cartes Anime
            'filmCount' => $totalFilmCards,   // Total des cartes Film
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

        // Fusionner les collections de cartes
        $allUserCards = array_merge($userCardAnimes, $userCardFilms);

        // Filtrage des cartes
        $filteredCards = array_filter($allUserCards, function ($userCard) use ($search, $selectedRarity, $selectedSection) {
            // Déterminer dynamiquement si c'est une carte Anime ou Film
            if (method_exists($userCard, 'getCardAnime') && $userCard->getCardAnime()) {
                $card = $userCard->getCardAnime();
                $type = 'anime';
            } elseif (method_exists($userCard, 'getCardFilm') && $userCard->getCardFilm()) {
                $card = $userCard->getCardFilm();
                $type = 'film';
            } else {
                return false;
            }

            // Filtrer selon la section (anime ou film)
            if ($selectedSection === 'anime' && $type !== 'anime') return false;
            if ($selectedSection === 'film' && $type !== 'film') return false;

            // Filtrer par rareté
            if ($selectedRarity && $card->getRarity() && $card->getRarity()->getLibelle() !== $selectedRarity) {
                return false;
            }

            // Filtrer selon la recherche texte
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
