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
use App\Service\WishlistService;
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
    public const RARITY_WEIGHTS = [
        1 => 40, // Communes
        2 => 35, // Rares
        3 => 20, // Épiques
        4 => 5,  // Legendaires
    ];

    public const RARITY_WEIGHTS_333 = [
        1 => 33, // Communes
        2 => 33, // Rares
        3 => 33, // Épiques
        4 => 1,  // Legendaires
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
        $titles = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('ca')
            ->select('a.id, a.nom')
            ->join('ca.anime', 'a')
            ->groupBy('a.id, a.nom')
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('wheel/spin.html.twig', [
            'user' => $user,
            'type' => 'anime',
            'rarities' => $this->getEligibleRaritiesAsArray($entityManager, self::RARITY_WEIGHTS),
            'rarities333' => $this->getEligibleRaritiesAsArray($entityManager, self::RARITY_WEIGHTS_333),
            'titles' => $titles,
        ]);
    }

    #[Route('/film/user/{id}', name: 'app_wheel_film_user', methods: ['GET'])]
    public function filmSpinPage(User $user, EntityManagerInterface $entityManager): Response
    {
        $titles = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('cf')
            ->select('f.id, f.nom')
            ->join('cf.film', 'f')
            ->groupBy('f.id, f.nom')
            ->orderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('wheel/spin.html.twig', [
            'user' => $user,
            'type' => 'film',
            'rarities' => $this->getEligibleRaritiesAsArray($entityManager, self::RARITY_WEIGHTS),
            'rarities333' => $this->getEligibleRaritiesAsArray($entityManager, self::RARITY_WEIGHTS_333),
            'titles' => $titles,
        ]);
    }

    #[Route('/spin-rarity', name: 'app_wheel_spin_rarity', methods: ['POST'])]
    public function spinRarity(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $mode = $request->request->get('mode');
        $baseWeights = $mode === '333' ? self::RARITY_WEIGHTS_333 : self::RARITY_WEIGHTS;

        $minRarityId = $request->request->get('minRarityId');
        $minRarityId = ($minRarityId !== null && $minRarityId !== '') ? (int) $minRarityId : null;
        if ($minRarityId !== null && !array_key_exists($minRarityId, $baseWeights)) {
            return $this->json(['success' => false, 'message' => 'Rareté minimum invalide.'], 400);
        }

        $weights = $this->getWeightsForMinimumRarity($baseWeights, $minRarityId);

        $roll = mt_rand(1, 100);
        $cumulative = 0;
        $chosenId = array_key_first($weights);

        foreach ($weights as $id => $weight) {
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

        $titleId = $request->request->get('titleId');
        $titleId = ($titleId !== null && $titleId !== '') ? (int) $titleId : null;

        $eligible = $this->getEligibleAnimeCards($entityManager, $rarityId, $titleId);
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

        $titleId = $request->request->get('titleId');
        $titleId = ($titleId !== null && $titleId !== '') ? (int) $titleId : null;

        $eligible = $this->getEligibleFilmCards($entityManager, $rarityId, $titleId);
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

    // Ne fait que trancher l'étape de "quitte ou double" (gagné/perdu) — le tirage de la
    // carte elle-même se fait ensuite via /spin-card comme n'importe quel autre tirage,
    // une fois que le joueur a décidé de s'arrêter à la rareté atteinte.
    #[Route('/double-chance', name: 'app_wheel_double_chance', methods: ['POST'])]
    public function doubleChance(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $step = $request->request->get('step');
        $chance = match ($step) {
            'to-rare' => 50,
            'to-epique' => 25,
            default => null,
        };
        if ($chance === null) {
            return $this->json(['success' => false, 'message' => 'Étape invalide.'], 400);
        }

        $won = mt_rand(1, 100) <= $chance;
        $rarityId = $step === 'to-rare' ? 2 : 3;

        return $this->json([
            'success' => true,
            'won' => $won,
            'rarityId' => $won ? $rarityId : null,
        ]);
    }

    #[Route('/anime/user/{id}/attribuer', name: 'app_wheel_anime_confirm', methods: ['POST'])]
    public function animeConfirm(User $user, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService, WishlistService $wishlistService): JsonResponse
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
        $userCard->setObtainedAt(new \DateTimeImmutable());

        $entityManager->flush();
        $badgeService->refreshCollectorBadges($user);
        $wishlistService->removeAnimeCardFromWishlist($user, $card);

        return $this->json(['success' => true, 'message' => "✅ {$card->getNom()} attribuée à {$user->getPseudo()} !"]);
    }

    #[Route('/film/user/{id}/attribuer', name: 'app_wheel_film_confirm', methods: ['POST'])]
    public function filmConfirm(User $user, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService, WishlistService $wishlistService): JsonResponse
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
        $userCard->setObtainedAt(new \DateTimeImmutable());

        $entityManager->flush();
        $badgeService->refreshCollectorBadges($user);
        $wishlistService->removeFilmCardFromWishlist($user, $card);

        return $this->json(['success' => true, 'message' => "✅ {$card->getNom()} attribuée à {$user->getPseudo()} !"]);
    }

    /**
     * @return array<int, array{id: int, libelle: string, weight: int}>
     */
    private function getEligibleRaritiesAsArray(EntityManagerInterface $entityManager, array $weights): array
    {
        $rarities = $entityManager->getRepository(Rarities::class)
            ->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', array_keys($weights))
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Rarities $r) => [
            'id' => $r->getId(),
            'libelle' => $r->getLibelle(),
            'weight' => $weights[$r->getId()],
        ], $rarities);
    }

    /**
     * Applique un plancher de rareté à une table de poids de base. Le taux de
     * Légendaire ne bouge jamais ; seules les raretés en dessous se
     * redistribuent le reste proportionnellement entre elles.
     *
     * @return array<int, float|int>
     */
    private function getWeightsForMinimumRarity(array $baseWeights, ?int $minRarityId): array
    {
        if ($minRarityId === null) {
            return $baseWeights;
        }

        $filtered = array_filter(
            $baseWeights,
            fn (int $id) => $id >= $minRarityId,
            ARRAY_FILTER_USE_KEY
        );

        if ($filtered === []) {
            return $baseWeights;
        }

        if (count($filtered) === 1) {
            return [array_key_first($filtered) => 100];
        }

        $legendaryId = 4;
        if (array_key_exists($legendaryId, $filtered)) {
            $legendaryWeight = $baseWeights[$legendaryId];
            $others = array_diff_key($filtered, [$legendaryId => true]);
            $othersTotal = array_sum($others);
            $remainingBudget = 100 - $legendaryWeight;

            $result = $othersTotal > 0
                ? array_map(fn (int $weight) => $weight / $othersTotal * $remainingBudget, $others)
                : [];
            $result[$legendaryId] = $legendaryWeight;
            ksort($result);

            return $result;
        }

        $total = array_sum($filtered);

        return array_map(fn (int $weight) => $weight / $total * 100, $filtered);
    }

    /**
     * @return CardAnime[]
     */
    private function getEligibleAnimeCards(EntityManagerInterface $entityManager, int $rarityId, ?int $titleId = null): array
    {
        $qb = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('ca')
            ->where('ca.rarity = :rarityId')
            ->setParameter('rarityId', $rarityId);

        if ($titleId !== null) {
            $qb->andWhere('IDENTITY(ca.anime) = :titleId')
                ->setParameter('titleId', $titleId);
        }

        $cards = $qb->getQuery()->getResult();

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
    private function getEligibleFilmCards(EntityManagerInterface $entityManager, int $rarityId, ?int $titleId = null): array
    {
        $qb = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('cf')
            ->where('cf.rarity = :rarityId')
            ->setParameter('rarityId', $rarityId);

        if ($titleId !== null) {
            $qb->andWhere('IDENTITY(cf.film) = :titleId')
                ->setParameter('titleId', $titleId);
        }

        $cards = $qb->getQuery()->getResult();

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
