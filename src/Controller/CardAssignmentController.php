<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\CardType;
use App\Exception\StockEpuiseException;
use App\Repository\UserRepository;
use App\Service\CardAttributionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/assign-cards')]
#[IsGranted('ROLE_ADMIN')]
class CardAssignmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardAttributionService $cardAttributionService,
    ) {
    }

    // Page de sélection : Anime, Film ou Jeu vidéo
    #[Route('/', name: 'app_assign_cards_choice')]
    public function choice(): Response
    {
        return $this->render('card_assignment/choice.html.twig');
    }

    // Liste des utilisateurs pour attribuer des cartes d'animé
    #[Route('/anime/users', name: 'app_assign_anime_users')]
    public function animeUsers(UserRepository $userRepository): Response
    {
        return $this->renderUsers($userRepository, CardType::Anime);
    }

    // Liste des utilisateurs pour attribuer des cartes de film
    #[Route('/film/users', name: 'app_assign_film_users')]
    public function filmUsers(UserRepository $userRepository): Response
    {
        return $this->renderUsers($userRepository, CardType::Film);
    }

    // Liste des utilisateurs pour attribuer des cartes de jeu vidéo
    #[Route('/jeu/users', name: 'app_assign_jeu_users')]
    public function jeuUsers(UserRepository $userRepository): Response
    {
        return $this->renderUsers($userRepository, CardType::Jeu);
    }

    // Formulaire d'attribution de cartes d'animé à un utilisateur
    #[Route('/anime/user/{id}', name: 'app_assign_anime_to_user')]
    public function assignAnimeToUser(User $user, Request $request): Response
    {
        return $this->assign($user, $request, CardType::Anime);
    }

    // Formulaire d'attribution de cartes de film à un utilisateur
    #[Route('/film/user/{id}', name: 'app_assign_film_to_user')]
    public function assignFilmToUser(User $user, Request $request): Response
    {
        return $this->assign($user, $request, CardType::Film);
    }

    // Formulaire d'attribution de cartes de jeu vidéo à un utilisateur
    #[Route('/jeu/user/{id}', name: 'app_assign_jeu_to_user')]
    public function assignJeuToUser(User $user, Request $request): Response
    {
        return $this->assign($user, $request, CardType::Jeu);
    }

    private function renderUsers(UserRepository $userRepository, CardType $type): Response
    {
        $users = $userRepository->createQueryBuilder('u')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('card_assignment/users_list.html.twig', [
            'users' => $users,
            'type' => $type->value,
        ]);
    }

    private function assign(User $user, Request $request, CardType $type): Response
    {
        if ($request->isMethod('POST')) {
            $this->handleAssignment($user, $request, $type);

            // Post/Redirect/Get : un F5 ne doit pas rejouer l'attribution (et la notif Discord).
            return $this->redirect($request->getRequestUri());
        }

        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;

        // Cartes de la famille, avec recherche sur le nom de la carte ou de l'œuvre
        $qb = $this->entityManager->getRepository($type->cardClass())
            ->createQueryBuilder('c')
            ->leftJoin('c.' . $type->titleProperty(), 't')
            ->leftJoin('c.rarity', 'r');

        if (!empty($search)) {
            $qb->andWhere('c.nom LIKE :search OR t.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $totalCards = (clone $qb)->select('count(c.id)')->getQuery()->getSingleScalarResult();

        $cards = $qb->orderBy('r.id', 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('card_assignment/assign_cards.html.twig', [
            'user' => $user,
            'cards' => $cards,
            'type' => $type->value,
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => ceil($totalCards / $limit),
        ]);
    }

    private function handleAssignment(User $user, Request $request, CardType $type): void
    {
        if (!$this->isCsrfTokenValid('assign-card' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', '❌ Jeton de sécurité invalide.');

            return;
        }

        $cardId = $request->request->get('card_id');
        $quantity = (int) $request->request->get('quantity', 1);
        if (!$cardId || $quantity < 1) {
            return;
        }

        $card = $this->entityManager->getRepository($type->cardClass())->find($cardId);
        if (!$card) {
            return;
        }

        try {
            $this->cardAttributionService->attribuer($user, $card, $quantity);
            $this->addFlash('success', "✅ {$quantity}x {$card->getNom()} attribuée(s) à {$user->getPseudo()} !");
        } catch (StockEpuiseException $e) {
            $this->addFlash('error', "❌ Stock insuffisant pour {$card->getNom()} : {$e->getRestant()} exemplaire(s) restant(s).");
        }
    }
}
