<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserCardFilm;
use App\Repository\UserRepository;
use App\Repository\UserCardAnimeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        EntityManagerInterface $entityManager
    ): Response {
        // Récupère les cartes animées de l'utilisateur, triées par la méthode personnalisée
        $userCardAnimes = $userCardAnimeRepository->findByUserSorted($user);
        
        // Récupère les cartes de films de l'utilisateur avec une requête qui trie par rareté puis par ID
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

        return $this->render('collection/player_collection.html.twig', [
            'user' => $user,
            'userCardAnimes' => $userCardAnimes,
            'userCardFilms' => $userCardFilms,
        ]);
    }
}