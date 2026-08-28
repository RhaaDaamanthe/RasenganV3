<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    // Liste des utilisateurs pour retirer des cartes
    #[Route('/remove-cards', name: 'app_remove_cards_users')]
    public function removeCardsUsersList(UserRepository $userRepository): Response
    {
        $users = $userRepository->createQueryBuilder('u')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('user_management/remove_cards_users.html.twig', [
            'users' => $users,
        ]);
    }

    // Voir et retirer les cartes d'un utilisateur
    #[Route('/remove-cards/{id}', name: 'app_remove_user_cards')]
    public function removeUserCards(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $search = $request->query->get('search', '');
        $section = $request->query->get('section', '');

        // Récupérer les cartes d'animé de l'utilisateur
        $qbAnime = $entityManager->getRepository(UserCardAnime::class)
            ->createQueryBuilder('uca')
            ->leftJoin('uca.cardAnime', 'ca')
            ->leftJoin('ca.anime', 'a')
            ->where('uca.user = :user')
            ->setParameter('user', $user);

        if (!empty($search)) {
            $qbAnime->andWhere('ca.nom LIKE :search OR a.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $userCardAnimes = !in_array($section, ['film', 'jeu'], true) ? $qbAnime->getQuery()->getResult() : [];

        // Récupérer les cartes de film de l'utilisateur
        $qbFilm = $entityManager->getRepository(UserCardFilm::class)
            ->createQueryBuilder('ucf')
            ->leftJoin('ucf.cardFilm', 'cf')
            ->leftJoin('cf.film', 'f')
            ->where('ucf.user = :user')
            ->setParameter('user', $user);

        if (!empty($search)) {
            $qbFilm->andWhere('cf.nom LIKE :search OR f.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $userCardFilms = !in_array($section, ['anime', 'jeu'], true) ? $qbFilm->getQuery()->getResult() : [];

        // Récupérer les cartes de jeu vidéo de l'utilisateur
        $qbJeu = $entityManager->getRepository(UserCardJeu::class)
            ->createQueryBuilder('ucj')
            ->leftJoin('ucj.cardJeu', 'cj')
            ->leftJoin('cj.jeu', 'j')
            ->where('ucj.user = :user')
            ->setParameter('user', $user);

        if (!empty($search)) {
            $qbJeu->andWhere('cj.nom LIKE :search OR j.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $userCardJeus = !in_array($section, ['anime', 'film'], true) ? $qbJeu->getQuery()->getResult() : [];

        // Traitement de la suppression
        if ($request->isMethod('POST')) {
            $userCardId = $request->request->get('user_card_id');
            $type = $request->request->get('type');
            $action = $request->request->get('action'); // 'remove_one' ou 'remove_all'

            if ($userCardId && $type) {
                $userCard = match ($type) {
                    'anime' => $entityManager->getRepository(UserCardAnime::class)->find($userCardId),
                    'jeu' => $entityManager->getRepository(UserCardJeu::class)->find($userCardId),
                    default => $entityManager->getRepository(UserCardFilm::class)->find($userCardId),
                };

                if ($userCard) {
                    $cardName = match ($type) {
                        'anime' => $userCard->getCardAnime()->getNom(),
                        'jeu' => $userCard->getCardJeu()->getNom(),
                        default => $userCard->getCardFilm()->getNom(),
                    };
                    
                    if ($action === 'remove_all' || $userCard->getQuantity() <= 1) {
                        // Supprimer complètement
                        $entityManager->remove($userCard);
                        $this->addFlash('success', "✅ Toutes les cartes '{$cardName}' ont été retirées à {$user->getPseudo()}");
                    } else {
                        // Retirer 1 seule carte
                        $userCard->setQuantity($userCard->getQuantity() - 1);
                        $this->addFlash('success', "✅ 1x '{$cardName}' retirée à {$user->getPseudo()}");
                    }
                    
                    $entityManager->flush();
                    return $this->redirectToRoute('app_remove_user_cards', ['id' => $user->getId()]);
                }
            }
        }

        return $this->render('user_management/remove_cards.html.twig', [
            'user' => $user,
            'userCardAnimes' => $userCardAnimes,
            'userCardFilms' => $userCardFilms,
            'userCardJeus' => $userCardJeus,
            'search' => $search,
            'selectedSection' => $section,
        ]);
    }

    // Liste des utilisateurs pour la gestion
    #[Route('/manage', name: 'app_manage_users')]
    public function manageUsers(UserRepository $userRepository): Response
    {
        $users = $userRepository->createQueryBuilder('u')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('user_management/manage_users.html.twig', [
            'users' => $users,
        ]);
    }

    // Renseigner ou effacer l'identifiant Discord d'un joueur, pour pouvoir le
    // mentionner lors d'un drop. Doublon volontaire du champ present dans
    // /parametres : le joueur peut le faire lui-meme, l'admin peut depanner.
    #[Route('/discord-id/{id}', name: 'app_set_user_discord_id', methods: ['POST'])]
    public function setUserDiscordId(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('set-discord-id' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', '❌ Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_manage_users');
        }

        $discordId = trim((string) $request->request->get('discord_id'));

        if ($discordId === '') {
            $user->setDiscordId(null);
            $entityManager->flush();
            $this->addFlash('success', "✅ Identifiant Discord de {$user->getPseudo()} effacé.");

            return $this->redirectToRoute('app_manage_users');
        }

        if (!preg_match('/^[0-9]{17,20}$/', $discordId)) {
            $this->addFlash('error', '❌ Un identifiant Discord est une suite de 17 à 20 chiffres.');

            return $this->redirectToRoute('app_manage_users');
        }

        $user->setDiscordId($discordId);
        $entityManager->flush();
        $this->addFlash('success', "✅ Identifiant Discord de {$user->getPseudo()} enregistré !");

        return $this->redirectToRoute('app_manage_users');
    }

    // Réinitialiser le mot de passe d'un utilisateur
    #[Route('/reset-password/{id}', name: 'app_reset_user_password', methods: ['POST'])]
    public function resetUserPassword(
        User $user,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $newPassword = $request->request->get('new_password');

        if ($newPassword && strlen($newPassword) >= 6) {
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $entityManager->flush();

            $this->addFlash('success', "✅ Mot de passe de {$user->getPseudo()} réinitialisé avec succès !");
        } else {
            $this->addFlash('error', "❌ Le mot de passe doit contenir au moins 6 caractères.");
        }

        return $this->redirectToRoute('app_manage_users');
    }
}