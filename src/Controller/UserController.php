<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserCardFilm;
use App\Form\ProfileSettingsType;
use App\Repository\UserCardAnimeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-compte')]
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_user_profile', methods: ['GET'])]
    public function showProfile(): Response
    {
        return $this->render('user/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/parametres', name: 'app_profile_settings', methods: ['GET', 'POST'])]
    public function settings(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserCardAnimeRepository $userCardAnimeRepository
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ProfileSettingsType::class, $user);
        $form->handleRequest($request);

        // RÉCUPÉRER LES CARTES DE L'UTILISATEUR
        $userCardAnimes = $userCardAnimeRepository->findByUserSorted($user);
        $userCardFilms = $entityManager->getRepository(UserCardFilm::class)->findBy(['user' => $user]);

        $userCards = [];
        foreach ($userCardAnimes as $uca) {
            $userCards[] = $uca->getCardAnime();
        }
        foreach ($userCardFilms as $ucf) {
            $userCards[] = $ucf->getCardFilm();
        }

        // TRI PAR RARETÉ (Commun → Légendaire)
        $rarityOrder = [
            'Commun' => 1,
            'Rare' => 2,
            'Épique' => 3,
            'Légendaire' => 4,
        ];

        usort($userCards, function ($a, $b) use ($rarityOrder) {
            $rarityA = $a->getRarity()?->getLibelle() ?? 'Inconnue';
            $rarityB = $b->getRarity()?->getLibelle() ?? 'Inconnue';
            $orderA = $rarityOrder[$rarityA] ?? 99;
            $orderB = $rarityOrder[$rarityB] ?? 99;
            return $orderA <=> $orderB;
        });

        // GROUPER PAR RARETÉ
        $cardsByRarity = [];
        foreach ($userCards as $card) {
            $rarity = $card->getRarity()?->getLibelle() ?? 'Inconnue';
            $cardsByRarity[$rarity][] = $card;
        }

        // TRAITEMENT DU FORMULAIRE (pseudo, titre, mdp, image)
        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('user/settings.html.twig', [
            'form' => $form->createView(),
            'cardsByRarity' => $cardsByRarity,     // CARTES TRIÉES PAR RARETÉ
            'currentImage' => $user->getImageCollection(),
        ]);
    }
}