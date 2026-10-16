<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\Rarities;
use App\Entity\User;
use App\Enum\CardType;
use App\Exception\StockEpuiseException;
use App\Repository\UserRepository;
use App\Service\CardAttributionService;
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

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // --- Choix du joueur ---------------------------------------------------------------

    #[Route('/anime/users', name: 'app_wheel_anime_users', methods: ['GET'])]
    public function animeUsers(UserRepository $userRepository): Response
    {
        return $this->renderUsers($userRepository, CardType::Anime);
    }

    #[Route('/film/users', name: 'app_wheel_film_users', methods: ['GET'])]
    public function filmUsers(UserRepository $userRepository): Response
    {
        return $this->renderUsers($userRepository, CardType::Film);
    }

    #[Route('/jeu/users', name: 'app_wheel_jeu_users', methods: ['GET'])]
    public function jeuUsers(UserRepository $userRepository): Response
    {
        return $this->renderUsers($userRepository, CardType::Jeu);
    }

    // --- Page de la roue ---------------------------------------------------------------

    #[Route('/anime/user/{id}', name: 'app_wheel_anime_user', methods: ['GET'])]
    public function animeSpinPage(User $user): Response
    {
        return $this->renderSpinPage($user, CardType::Anime);
    }

    #[Route('/film/user/{id}', name: 'app_wheel_film_user', methods: ['GET'])]
    public function filmSpinPage(User $user): Response
    {
        return $this->renderSpinPage($user, CardType::Film);
    }

    #[Route('/jeu/user/{id}', name: 'app_wheel_jeu_user', methods: ['GET'])]
    public function jeuSpinPage(User $user): Response
    {
        return $this->renderSpinPage($user, CardType::Jeu);
    }

    // --- Tirages -----------------------------------------------------------------------

    #[Route('/spin-rarity', name: 'app_wheel_spin_rarity', methods: ['POST'])]
    public function spinRarity(Request $request): JsonResponse
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

        $rarity = $this->entityManager->getRepository(Rarities::class)->find($chosenId);
        if ($rarity === null) {
            return $this->json(['success' => false, 'message' => 'Rareté introuvable en base.'], 500);
        }

        return $this->json([
            'success' => true,
            'rarityId' => $rarity->getId(),
            'libelle' => $rarity->getLibelle(),
        ]);
    }

    #[Route('/anime/user/{id}/spin-card', name: 'app_wheel_anime_spin_card', methods: ['POST'])]
    public function animeSpinCard(User $user, Request $request, Packages $packages): JsonResponse
    {
        return $this->spinCard($user, $request, $packages, CardType::Anime);
    }

    #[Route('/film/user/{id}/spin-card', name: 'app_wheel_film_spin_card', methods: ['POST'])]
    public function filmSpinCard(User $user, Request $request, Packages $packages): JsonResponse
    {
        return $this->spinCard($user, $request, $packages, CardType::Film);
    }

    #[Route('/jeu/user/{id}/spin-card', name: 'app_wheel_jeu_spin_card', methods: ['POST'])]
    public function jeuSpinCard(User $user, Request $request, Packages $packages): JsonResponse
    {
        return $this->spinCard($user, $request, $packages, CardType::Jeu);
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

    // --- Attribution -------------------------------------------------------------------

    #[Route('/anime/user/{id}/attribuer', name: 'app_wheel_anime_confirm', methods: ['POST'])]
    public function animeConfirm(User $user, Request $request, CardAttributionService $cardAttributionService): JsonResponse
    {
        return $this->confirm($user, $request, $cardAttributionService, CardType::Anime);
    }

    #[Route('/film/user/{id}/attribuer', name: 'app_wheel_film_confirm', methods: ['POST'])]
    public function filmConfirm(User $user, Request $request, CardAttributionService $cardAttributionService): JsonResponse
    {
        return $this->confirm($user, $request, $cardAttributionService, CardType::Film);
    }

    #[Route('/jeu/user/{id}/attribuer', name: 'app_wheel_jeu_confirm', methods: ['POST'])]
    public function jeuConfirm(User $user, Request $request, CardAttributionService $cardAttributionService): JsonResponse
    {
        return $this->confirm($user, $request, $cardAttributionService, CardType::Jeu);
    }

    // --- Implémentation commune aux trois familles -------------------------------------

    private function renderUsers(UserRepository $userRepository, CardType $type): Response
    {
        $users = $userRepository->createQueryBuilder('u')->orderBy('u.pseudo', 'ASC')->getQuery()->getResult();

        return $this->render('wheel/users.html.twig', ['users' => $users, 'type' => $type->value]);
    }

    private function renderSpinPage(User $user, CardType $type): Response
    {
        // Œuvres ayant au moins une carte, pour le filtre "tirer dans une œuvre précise".
        $titles = $this->entityManager->getRepository($type->cardClass())
            ->createQueryBuilder('c')
            ->select('t.id, t.nom')
            ->join('c.' . $type->titleProperty(), 't')
            ->groupBy('t.id, t.nom')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('wheel/spin.html.twig', [
            'user' => $user,
            'type' => $type->value,
            'rarities' => $this->getEligibleRaritiesAsArray(self::RARITY_WEIGHTS),
            'rarities333' => $this->getEligibleRaritiesAsArray(self::RARITY_WEIGHTS_333),
            'titles' => $titles,
        ]);
    }

    private function spinCard(User $user, Request $request, Packages $packages, CardType $type): JsonResponse
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

        $eligible = $this->getEligibleCards($type, $rarityId, $titleId);
        if (count($eligible) === 0) {
            return $this->json(['success' => false, 'message' => 'Toutes les cartes de cette rareté sont déjà complètes.']);
        }

        $winner = $eligible[array_rand($eligible)];

        $pool = array_values(array_filter($eligible, fn (CardAnime|CardFilm|CardJeu $c) => $c->getId() !== $winner->getId()));
        shuffle($pool);

        return $this->json([
            'success' => true,
            'winner' => $this->cardToArray($winner, $packages),
            'wishlisted' => $type->wishlistOf($user)->contains($winner),
            'pool' => array_map(fn (CardAnime|CardFilm|CardJeu $c) => $this->cardToArray($c, $packages), array_slice($pool, 0, 11)),
        ]);
    }

    private function confirm(User $user, Request $request, CardAttributionService $cardAttributionService, CardType $type): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wheel-action', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $card = $this->entityManager->getRepository($type->cardClass())->find((int) $request->request->get('cardId'));
        if (!$card) {
            return $this->json(['success' => false, 'message' => 'Carte introuvable.'], 400);
        }

        try {
            $cardAttributionService->attribuer($user, $card);
        } catch (StockEpuiseException) {
            return $this->json(['success' => false, 'message' => 'Cette carte est maintenant en rupture de stock.']);
        }

        return $this->json(['success' => true, 'message' => "✅ {$card->getNom()} attribuée à {$user->getPseudo()} !"]);
    }

    /**
     * @return array<int, array{id: int, libelle: string, weight: int}>
     */
    private function getEligibleRaritiesAsArray(array $weights): array
    {
        $rarities = $this->entityManager->getRepository(Rarities::class)
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
     * Cartes d'une rareté (et éventuellement d'une œuvre) dont le stock n'est pas épuisé.
     *
     * Une seule requête : le total distribué est calculé par une sous-requête corrélée
     * au lieu d'un SUM lancé carte par carte.
     *
     * @return array<int, CardAnime|CardFilm|CardJeu>
     */
    private function getEligibleCards(CardType $type, int $rarityId, ?int $titleId = null): array
    {
        $distributed = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(uc.quantity), 0)')
            ->from($type->userCardClass(), 'uc')
            ->where(sprintf('uc.%s = c', $type->cardProperty()));

        $qb = $this->entityManager->getRepository($type->cardClass())
            ->createQueryBuilder('c')
            ->where('c.rarity = :rarityId')
            ->andWhere('c.quantity > (' . $distributed->getDQL() . ')')
            ->setParameter('rarityId', $rarityId);

        if ($titleId !== null) {
            $qb->andWhere(sprintf('IDENTITY(c.%s) = :titleId', $type->titleProperty()))
                ->setParameter('titleId', $titleId);
        }

        return $qb->getQuery()->getResult();
    }

    private function cardToArray(CardAnime|CardFilm|CardJeu $card, Packages $packages): array
    {
        return [
            'id' => $card->getId(),
            'nom' => $card->getNom(),
            'imagePath' => $card->getImagePath() ? $packages->getUrl($card->getImagePath()) : null,
        ];
    }
}
