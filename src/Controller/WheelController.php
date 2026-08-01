<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\Rarities;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/wheel')]
#[IsGranted('ROLE_ADMIN')]
class WheelController extends AbstractController
{
    private const RARITY_WEIGHTS = [
        1 => 40, // Communes
        2 => 35, // Rares
        3 => 20, // Épiques
        4 => 5,  // Legendaires
    ];

    #[Route('/anime/users', name: 'app_wheel_anime_users', methods: ['GET'])]
    public function animeUsers(UserRepository $userRepository): Response
    {
        $users = $userRepository->createQueryBuilder('u')->orderBy('u.pseudo', 'ASC')->getQuery()->getResult();

        return $this->render('wheel/users.html.twig', ['users' => $users, 'type' => 'anime']);
    }

    #[Route('/film/users', name: 'app_wheel_film_users', methods: ['GET'])]
    public function filmUsers(UserRepository $userRepository): Response
    {
        $users = $userRepository->createQueryBuilder('u')->orderBy('u.pseudo', 'ASC')->getQuery()->getResult();

        return $this->render('wheel/users.html.twig', ['users' => $users, 'type' => 'film']);
    }

    #[Route('/anime/user/{id}', name: 'app_wheel_anime_user', methods: ['GET'])]
    public function animeSpinPage(User $user, EntityManagerInterface $entityManager): Response
    {
        return $this->render('wheel/spin.html.twig', [
            'user' => $user,
            'type' => 'anime',
            'rarities' => $this->getEligibleRaritiesAsArray($entityManager),
        ]);
    }

    #[Route('/film/user/{id}', name: 'app_wheel_film_user', methods: ['GET'])]
    public function filmSpinPage(User $user, EntityManagerInterface $entityManager): Response
    {
        return $this->render('wheel/spin.html.twig', [
            'user' => $user,
            'type' => 'film',
            'rarities' => $this->getEligibleRaritiesAsArray($entityManager),
        ]);
    }

    #[Route('/spin-rarity', name: 'app_wheel_spin_rarity', methods: ['POST'])]
    public function spinRarity(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $roll = mt_rand(1, 100);
        $cumulative = 0;
        $chosenId = array_key_first(self::RARITY_WEIGHTS);

        foreach (self::RARITY_WEIGHTS as $id => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                $chosenId = $id;
                break;
            }
        }

        $rarity = $entityManager->getRepository(Rarities::class)->find($chosenId);

        return $this->json([
            'success' => true,
            'rarityId' => $rarity->getId(),
            'libelle' => $rarity->getLibelle(),
        ]);
    }

    #[Route('/anime/user/{id}/spin-card', name: 'app_wheel_anime_spin_card', methods: ['POST'])]
    public function animeSpinCard(User $user, Request $request, EntityManagerInterface $entityManager, Packages $packages): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $rarityId = (int) $request->request->get('rarityId');
        if (!array_key_exists($rarityId, self::RARITY_WEIGHTS)) {
            return $this->json(['success' => false, 'message' => 'Rareté invalide.'], 400);
        }

        $eligible = $this->getEligibleAnimeCards($entityManager, $rarityId);
        if (count($eligible) === 0) {
            return $this->json(['success' => false, 'message' => 'Toutes les cartes de cette rareté sont déjà complètes.']);
        }

        $winner = $eligible[array_rand($eligible)];

        $pool = array_values(array_filter($eligible, fn (CardAnime $c) => $c->getId() !== $winner->getId()));
        shuffle($pool);

        return $this->json([
            'success' => true,
            'winner' => $this->cardToArray($winner, $packages),
            'pool' => array_map(fn (CardAnime $c) => $this->cardToArray($c, $packages), array_slice($pool, 0, 11)),
        ]);
    }

    #[Route('/film/user/{id}/spin-card', name: 'app_wheel_film_spin_card', methods: ['POST'])]
    public function filmSpinCard(User $user, Request $request, EntityManagerInterface $entityManager, Packages $packages): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $rarityId = (int) $request->request->get('rarityId');
        if (!array_key_exists($rarityId, self::RARITY_WEIGHTS)) {
            return $this->json(['success' => false, 'message' => 'Rareté invalide.'], 400);
        }

        $eligible = $this->getEligibleFilmCards($entityManager, $rarityId);
        if (count($eligible) === 0) {
            return $this->json(['success' => false, 'message' => 'Toutes les cartes de cette rareté sont déjà complètes.']);
        }

        $winner = $eligible[array_rand($eligible)];

        $pool = array_values(array_filter($eligible, fn (CardFilm $c) => $c->getId() !== $winner->getId()));
        shuffle($pool);

        return $this->json([
            'success' => true,
            'winner' => $this->cardToArray($winner, $packages),
            'pool' => array_map(fn (CardFilm $c) => $this->cardToArray($c, $packages), array_slice($pool, 0, 11)),
        ]);
    }

    #[Route('/anime/user/{id}/attribuer', name: 'app_wheel_anime_confirm', methods: ['POST'])]
    public function animeConfirm(User $user, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $card = $entityManager->getRepository(CardAnime::class)->find((int) $request->request->get('cardId'));
        if (!$card) {
            return $this->json(['success' => false, 'message' => 'Carte introuvable.'], 400);
        }

        $distributed = $entityManager->getRepository(UserCardAnime::class)
            ->createQueryBuilder('uca')
            ->select('SUM(uca.quantity)')
            ->where('uca.cardAnime = :card')
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();

        if (($distributed ?? 0) >= $card->getQuantity()) {
            return $this->json(['success' => false, 'message' => 'Cette carte est maintenant en rupture de stock.']);
        }

        $userCard = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
            'user' => $user,
            'cardAnime' => $card,
        ]);

        if ($userCard) {
            $userCard->setQuantity($userCard->getQuantity() + 1);
        } else {
            $userCard = new UserCardAnime();
            $userCard->setUser($user);
            $userCard->setCardAnime($card);
            $userCard->setQuantity(1);
            $entityManager->persist($userCard);
        }

        $entityManager->flush();
        $badgeService->refreshCollectorBadges($user);

        return $this->json(['success' => true, 'message' => "✅ {$card->getNom()} attribuée à {$user->getPseudo()} !"]);
    }

    #[Route('/film/user/{id}/attribuer', name: 'app_wheel_film_confirm', methods: ['POST'])]
    public function filmConfirm(User $user, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $card = $entityManager->getRepository(CardFilm::class)->find((int) $request->request->get('cardId'));
        if (!$card) {
            return $this->json(['success' => false, 'message' => 'Carte introuvable.'], 400);
        }

        $distributed = $entityManager->getRepository(UserCardFilm::class)
            ->createQueryBuilder('ucf')
            ->select('SUM(ucf.quantity)')
            ->where('ucf.cardFilm = :card')
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();

        if (($distributed ?? 0) >= $card->getQuantity()) {
            return $this->json(['success' => false, 'message' => 'Cette carte est maintenant en rupture de stock.']);
        }

        $userCard = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
            'user' => $user,
            'cardFilm' => $card,
        ]);

        if ($userCard) {
            $userCard->setQuantity($userCard->getQuantity() + 1);
        } else {
            $userCard = new UserCardFilm();
            $userCard->setUser($user);
            $userCard->setCardFilm($card);
            $userCard->setQuantity(1);
            $entityManager->persist($userCard);
        }

        $entityManager->flush();
        $badgeService->refreshCollectorBadges($user);

        return $this->json(['success' => true, 'message' => "✅ {$card->getNom()} attribuée à {$user->getPseudo()} !"]);
    }

    /**
     * @return array<int, array{id: int, libelle: string, weight: int}>
     */
    private function getEligibleRaritiesAsArray(EntityManagerInterface $entityManager): array
    {
        $rarities = $entityManager->getRepository(Rarities::class)
            ->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', array_keys(self::RARITY_WEIGHTS))
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Rarities $r) => [
            'id' => $r->getId(),
            'libelle' => $r->getLibelle(),
            'weight' => self::RARITY_WEIGHTS[$r->getId()],
        ], $rarities);
    }

    /**
     * @return CardAnime[]
     */
    private function getEligibleAnimeCards(EntityManagerInterface $entityManager, int $rarityId): array
    {
        $cards = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('ca')
            ->where('ca.rarity = :rarityId')
            ->setParameter('rarityId', $rarityId)
            ->getQuery()
            ->getResult();

        $eligible = [];
        foreach ($cards as $card) {
            $distributed = $entityManager->getRepository(UserCardAnime::class)
                ->createQueryBuilder('uca')
                ->select('SUM(uca.quantity)')
                ->where('uca.cardAnime = :card')
                ->setParameter('card', $card)
                ->getQuery()
                ->getSingleScalarResult();

            if (($distributed ?? 0) < $card->getQuantity()) {
                $eligible[] = $card;
            }
        }

        return $eligible;
    }

    /**
     * @return CardFilm[]
     */
    private function getEligibleFilmCards(EntityManagerInterface $entityManager, int $rarityId): array
    {
        $cards = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('cf')
            ->where('cf.rarity = :rarityId')
            ->setParameter('rarityId', $rarityId)
            ->getQuery()
            ->getResult();

        $eligible = [];
        foreach ($cards as $card) {
            $distributed = $entityManager->getRepository(UserCardFilm::class)
                ->createQueryBuilder('ucf')
                ->select('SUM(ucf.quantity)')
                ->where('ucf.cardFilm = :card')
                ->setParameter('card', $card)
                ->getQuery()
                ->getSingleScalarResult();

            if (($distributed ?? 0) < $card->getQuantity()) {
                $eligible[] = $card;
            }
        }

        return $eligible;
    }

    private function cardToArray(CardAnime|CardFilm $card, Packages $packages): array
    {
        return [
            'id' => $card->getId(),
            'nom' => $card->getNom(),
            'imagePath' => $card->getImagePath() ? $packages->getUrl($card->getImagePath()) : null,
        ];
    }
}
