<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Repository\UserRepository;
use App\Repository\CardAnimeRepository;
use App\Repository\CardFilmRepository;
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
        // Page de sélection : Anime ou Film
        #[Route('/', name: 'app_assign_cards_choice')]
        public function choice(): Response
        {
            return $this->render('card_assignment/choice.html.twig');
        }

    // Liste des utilisateurs pour attribuer des cartes d'animé
    #[Route('/anime/users', name: 'app_assign_anime_users')]
    public function animeUsers(UserRepository $userRepository): Response
    {
        $users = $userRepository->createQueryBuilder('u')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('card_assignment/users_list.html.twig', [
            'users' => $users,
            'type' => 'anime',
        ]);
    }

    // Liste des utilisateurs pour attribuer des cartes de film
    #[Route('/film/users', name: 'app_assign_film_users')]
    public function filmUsers(UserRepository $userRepository): Response
    {
        $users = $userRepository->createQueryBuilder('u')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('card_assignment/users_list.html.twig', [
            'users' => $users,
            'type' => 'film',
        ]);
    }

    // Formulaire d'attribution de cartes d'animé à un utilisateur
    #[Route('/anime/user/{id}', name: 'app_assign_anime_to_user')]
    public function assignAnimeToUser(
        User $user,
        Request $request,
        CardAnimeRepository $cardAnimeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Requête pour récupérer les cartes avec recherche
        $qb = $cardAnimeRepository->createQueryBuilder('ca')
            ->leftJoin('ca.anime', 'a')
            ->leftJoin('ca.rarity', 'r');

        if (!empty($search)) {
            $qb->andWhere('ca.nom LIKE :search OR a.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $totalCards = (clone $qb)->select('count(ca.id)')->getQuery()->getSingleScalarResult();
        
        $cards = $qb->orderBy('r.id', 'DESC')
            ->addOrderBy('ca.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalCards / $limit);

        // Traitement du formulaire
        if ($request->isMethod('POST')) {
            $cardId = $request->request->get('card_id');
            $quantity = (int) $request->request->get('quantity', 1);

            if ($cardId && $quantity > 0) {
                $card = $cardAnimeRepository->find($cardId);
                
                if ($card) {
                    // Vérifier si l'utilisateur possède déjà cette carte
                    $userCard = $entityManager->getRepository(UserCardAnime::class)
                        ->findOneBy(['user' => $user, 'cardAnime' => $card]);

                    if ($userCard) {
                        // Augmenter la quantité
                        $userCard->setQuantity($userCard->getQuantity() + $quantity);
                    } else {
                        // Créer une nouvelle attribution
                        $userCard = new UserCardAnime();
                        $userCard->setUser($user);
                        $userCard->setCardAnime($card);
                        $userCard->setQuantity($quantity);
                        $entityManager->persist($userCard);
                    }

                    $entityManager->flush();
                    $this->addFlash('success', "✅ {$quantity}x {$card->getNom()} attribuée(s) à {$user->getPseudo()} !");
                }
            }
        }

        return $this->render('card_assignment/assign_cards.html.twig', [
            'user' => $user,
            'cards' => $cards,
            'type' => 'anime',
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    // Formulaire d'attribution de cartes de film à un utilisateur
    #[Route('/film/user/{id}', name: 'app_assign_film_to_user')]
    public function assignFilmToUser(
        User $user,
        Request $request,
        CardFilmRepository $cardFilmRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Requête pour récupérer les cartes avec recherche
        $qb = $cardFilmRepository->createQueryBuilder('cf')
            ->leftJoin('cf.film', 'f')
            ->leftJoin('cf.rarity', 'r');

        if (!empty($search)) {
            $qb->andWhere('cf.nom LIKE :search OR f.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $totalCards = (clone $qb)->select('count(cf.id)')->getQuery()->getSingleScalarResult();
        
        $cards = $qb->orderBy('r.id', 'DESC')
            ->addOrderBy('cf.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalCards / $limit);

        // Traitement du formulaire
        if ($request->isMethod('POST')) {
            $cardId = $request->request->get('card_id');
            $quantity = (int) $request->request->get('quantity', 1);

            if ($cardId && $quantity > 0) {
                $card = $cardFilmRepository->find($cardId);
                
                if ($card) {
                    // Vérifier si l'utilisateur possède déjà cette carte
                    $userCard = $entityManager->getRepository(UserCardFilm::class)
                        ->findOneBy(['user' => $user, 'cardFilm' => $card]);

                    if ($userCard) {
                        // Augmenter la quantité
                        $userCard->setQuantity($userCard->getQuantity() + $quantity);
                    } else {
                        // Créer une nouvelle attribution
                        $userCard = new UserCardFilm();
                        $userCard->setUser($user);
                        $userCard->setCardFilm($card);
                        $userCard->setQuantity($quantity);
                        $entityManager->persist($userCard);
                    }

                    $entityManager->flush();
                    $this->addFlash('success', "✅ {$quantity}x {$card->getNom()} attribuée(s) à {$user->getPseudo()} !");
                }
            }
        }

        return $this->render('card_assignment/assign_cards.html.twig', [
            'user' => $user,
            'cards' => $cards,
            'type' => 'film',
            'search' => $search,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}