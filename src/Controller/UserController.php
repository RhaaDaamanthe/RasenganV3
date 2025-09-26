<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserCardFilm;
use App\Repository\UserCardAnimeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_user_profile', methods: ['GET'])]
    public function showProfile(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('user/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/mon-compte/modifier-carte', name: 'app_profile_edit', methods: ['GET'])]
    public function selectProfileCard(
        UserCardAnimeRepository $userCardAnimeRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupère toutes les cartes de l'utilisateur
        $userCardAnimes = $userCardAnimeRepository->findByUserSorted($user);
        $userCardFilms = $entityManager->getRepository(UserCardFilm::class)->findBy(['user' => $user]);

        $userCards = [];
        foreach ($userCardAnimes as $userCardAnime) {
            $userCards[] = $userCardAnime->getCardAnime();
        }
        foreach ($userCardFilms as $userCardFilm) {
            $userCards[] = $userCardFilm->getCardFilm();
        }

        return $this->render('user/profile_edit.html.twig', [
            'userCards' => $userCards,
            'currentUser' => $user,
        ]);
    }

    #[Route('/api/update-profile-picture', name: 'api_update_profile_picture', methods: ['POST'])]
public function updateProfilePicture(Request $request, EntityManagerInterface $entityManager): JsonResponse
{
    $user = $this->getUser();
    if (!$user) {
        return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
    }

    $data = json_decode($request->getContent(), true);
    $imagePath = $data['imagePath'] ?? null;

    if (!$imagePath) {
        return new JsonResponse(['error' => 'Chemin de l\'image manquant.'], Response::HTTP_BAD_REQUEST);
    }
    
    // Mettez à jour le chemin de l'image du profil de l'utilisateur
    $user->setImageCollection($imagePath);
    $entityManager->flush();

    // Générer l'URL de redirection
    $redirectUrl = $this->generateUrl('app_players_list'); // Remplacez par votre vraie route
    
    return new JsonResponse([
        'success' => 'Votre carte de profil a été mise à jour avec succès !',
        'redirect' => $redirectUrl
    ]);
}
}