<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardAnimeRequirement;
use App\Entity\CardFilm;
use App\Entity\CardFilmRequirement;
use App\Entity\CardJeu;
use App\Entity\CardJeuRequirement;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use App\Exception\CarteDejaPossedeeException;
use App\Exception\StockEpuiseException;
use App\Service\BadgeService;
use App\Service\CardAttributionService;
use App\Service\WishlistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MythicController extends AbstractController
{
    #[Route('/catalogue/anime/mythic/{id}/status', name: 'app_mythic_anime_status', methods: ['GET'])]
    public function animeStatus(CardAnime $card, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        $userCard = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
            'user' => $user,
            'cardAnime' => $card,
        ]);
        $alreadyOwned = $userCard !== null && $userCard->getQuantity() > 0;

        $recipe = $this->loadRecipe($entityManager, CardAnimeRequirement::class, $card);
        $ownedById = $this->ownedQuantities($entityManager, $user, UserCardAnime::class, 'cardAnime', $recipe);

        $requirements = [];
        $canUnlock = count($recipe) > 0;

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                $canUnlock = false;
                $requirements[] = [
                    'placeholder' => true,
                    'nom' => $requirement->getPlaceholderNom() ?? 'Carte à venir',
                    'needed' => $requirement->getQuantityRequired(),
                    'owned' => 0,
                    'ok' => false,
                ];
                continue;
            }

            $needed = $requirement->getQuantityRequired();

            $ownedQuantity = $ownedById[$requiredCard->getId()] ?? 0;
            $ok = $ownedQuantity >= $needed;

            // Toutes les alternatives sont renvoyées (pas seulement celle qui valide la
            // ligne) pour que le joueur sache quelles autres cartes sont acceptées.
            $viaAlt = null;
            $alternatives = [];
            foreach ($requirement->getAlternativeCards() as $altCard) {
                $altQuantity = $ownedById[$altCard->getId()] ?? 0;
                $alternatives[] = [
                    'nom' => $altCard->getNom(),
                    'owned' => $altQuantity,
                ];
                if (!$ok && $altQuantity >= $needed) {
                    $ok = true;
                    $viaAlt = $altCard;
                    $ownedQuantity = $altQuantity;
                }
            }

            if (!$ok) {
                $canUnlock = false;
            }

            $requirements[] = [
                'id' => $requiredCard->getId(),
                'nom' => $requiredCard->getNom(),
                'imagePath' => $requiredCard->getImagePath(),
                'needed' => $needed,
                'owned' => $ownedQuantity,
                'ok' => $ok,
                'wishlisted' => $user->getWishlistCardAnimes()->contains($requiredCard),
                'viaAlt' => $viaAlt?->getNom(),
                'alternatives' => $alternatives,
            ];
        }

        return $this->json([
            'nom' => $card->getNom(),
            'alreadyOwned' => $alreadyOwned,
            'canUnlock' => $canUnlock && !$alreadyOwned,
            'requirements' => $requirements,
        ]);
    }

    #[Route('/catalogue/anime/mythic/{id}/unlock', name: 'app_mythic_anime_unlock', methods: ['POST'])]
    public function animeUnlock(CardAnime $card, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService, WishlistService $wishlistService, CardAttributionService $cardAttributionService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('mythic-unlock', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $user = $this->getUser();

        $existing = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
            'user' => $user,
            'cardAnime' => $card,
        ]);
        if ($existing !== null && $existing->getQuantity() > 0) {
            return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
        }

        $recipe = $this->loadRecipe($entityManager, CardAnimeRequirement::class, $card);
        $ownedById = $this->ownedQuantities($entityManager, $user, UserCardAnime::class, 'cardAnime', $recipe);

        if (count($recipe) === 0) {
            return $this->json(['success' => false, 'message' => 'Aucune combinaison n\'est configurée pour cette carte.'], 400);
        }

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                return $this->json(['success' => false, 'message' => "Cette carte n'est pas encore complètement configurée."], 400);
            }

            $needed = $requirement->getQuantityRequired();

            $satisfied = ($ownedById[$requiredCard->getId()] ?? 0) >= $needed;

            if (!$satisfied) {
                foreach ($requirement->getAlternativeCards() as $altCard) {
                    if (($ownedById[$altCard->getId()] ?? 0) >= $needed) {
                        $satisfied = true;
                        break;
                    }
                }
            }

            if (!$satisfied) {
                return $this->json(['success' => false, 'message' => 'La combinaison n\'est pas complète.'], 400);
            }
        }

        try {
            $cardAttributionService->attribuer($user, $card, 1, true);
        } catch (CarteDejaPossedeeException) {
            return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
        } catch (StockEpuiseException) {
            return $this->json(['success' => false, 'message' => "Cette carte mythique n'est plus disponible."], 400);
        }

        $badgeService->refreshCollectorBadges($user);
        $wishlistService->removeAnimeCardFromWishlist($user, $card);

        return $this->json([
            'success' => true,
            'message' => 'Carte mythique débloquée !',
            'pseudo' => $user->getPseudo(),
        ]);
    }

    #[Route('/catalogue/film/mythic/{id}/status', name: 'app_mythic_film_status', methods: ['GET'])]
    public function filmStatus(CardFilm $card, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        $userCard = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
            'user' => $user,
            'cardFilm' => $card,
        ]);
        $alreadyOwned = $userCard !== null && $userCard->getQuantity() > 0;

        $recipe = $this->loadRecipe($entityManager, CardFilmRequirement::class, $card);
        $ownedById = $this->ownedQuantities($entityManager, $user, UserCardFilm::class, 'cardFilm', $recipe);

        $requirements = [];
        $canUnlock = count($recipe) > 0;

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                $canUnlock = false;
                $requirements[] = [
                    'placeholder' => true,
                    'nom' => $requirement->getPlaceholderNom() ?? 'Carte à venir',
                    'needed' => $requirement->getQuantityRequired(),
                    'owned' => 0,
                    'ok' => false,
                ];
                continue;
            }

            $needed = $requirement->getQuantityRequired();

            $ownedQuantity = $ownedById[$requiredCard->getId()] ?? 0;
            $ok = $ownedQuantity >= $needed;

            // Toutes les alternatives sont renvoyées (pas seulement celle qui valide la
            // ligne) pour que le joueur sache quelles autres cartes sont acceptées.
            $viaAlt = null;
            $alternatives = [];
            foreach ($requirement->getAlternativeCards() as $altCard) {
                $altQuantity = $ownedById[$altCard->getId()] ?? 0;
                $alternatives[] = [
                    'nom' => $altCard->getNom(),
                    'owned' => $altQuantity,
                ];
                if (!$ok && $altQuantity >= $needed) {
                    $ok = true;
                    $viaAlt = $altCard;
                    $ownedQuantity = $altQuantity;
                }
            }

            if (!$ok) {
                $canUnlock = false;
            }

            $requirements[] = [
                'id' => $requiredCard->getId(),
                'nom' => $requiredCard->getNom(),
                'imagePath' => $requiredCard->getImagePath(),
                'needed' => $needed,
                'owned' => $ownedQuantity,
                'ok' => $ok,
                'wishlisted' => $user->getWishlistCardFilms()->contains($requiredCard),
                'viaAlt' => $viaAlt?->getNom(),
                'alternatives' => $alternatives,
            ];
        }

        return $this->json([
            'nom' => $card->getNom(),
            'alreadyOwned' => $alreadyOwned,
            'canUnlock' => $canUnlock && !$alreadyOwned,
            'requirements' => $requirements,
        ]);
    }

    #[Route('/catalogue/film/mythic/{id}/unlock', name: 'app_mythic_film_unlock', methods: ['POST'])]
    public function filmUnlock(CardFilm $card, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService, WishlistService $wishlistService, CardAttributionService $cardAttributionService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('mythic-unlock', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $user = $this->getUser();

        $existing = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
            'user' => $user,
            'cardFilm' => $card,
        ]);
        if ($existing !== null && $existing->getQuantity() > 0) {
            return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
        }

        $recipe = $this->loadRecipe($entityManager, CardFilmRequirement::class, $card);
        $ownedById = $this->ownedQuantities($entityManager, $user, UserCardFilm::class, 'cardFilm', $recipe);

        if (count($recipe) === 0) {
            return $this->json(['success' => false, 'message' => 'Aucune combinaison n\'est configurée pour cette carte.'], 400);
        }

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                return $this->json(['success' => false, 'message' => "Cette carte n'est pas encore complètement configurée."], 400);
            }

            $needed = $requirement->getQuantityRequired();

            $satisfied = ($ownedById[$requiredCard->getId()] ?? 0) >= $needed;

            if (!$satisfied) {
                foreach ($requirement->getAlternativeCards() as $altCard) {
                    if (($ownedById[$altCard->getId()] ?? 0) >= $needed) {
                        $satisfied = true;
                        break;
                    }
                }
            }

            if (!$satisfied) {
                return $this->json(['success' => false, 'message' => 'La combinaison n\'est pas complète.'], 400);
            }
        }

        try {
            $cardAttributionService->attribuer($user, $card, 1, true);
        } catch (CarteDejaPossedeeException) {
            return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
        } catch (StockEpuiseException) {
            return $this->json(['success' => false, 'message' => "Cette carte mythique n'est plus disponible."], 400);
        }

        $badgeService->refreshCollectorBadges($user);
        $wishlistService->removeFilmCardFromWishlist($user, $card);

        return $this->json([
            'success' => true,
            'message' => 'Carte mythique débloquée !',
            'pseudo' => $user->getPseudo(),
        ]);
    }

    #[Route('/catalogue/jeu/mythic/{id}/status', name: 'app_mythic_jeu_status', methods: ['GET'])]
    public function jeuStatus(CardJeu $card, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        $userCard = $entityManager->getRepository(UserCardJeu::class)->findOneBy([
            'user' => $user,
            'cardJeu' => $card,
        ]);
        $alreadyOwned = $userCard !== null && $userCard->getQuantity() > 0;

        $recipe = $this->loadRecipe($entityManager, CardJeuRequirement::class, $card);
        $ownedById = $this->ownedQuantities($entityManager, $user, UserCardJeu::class, 'cardJeu', $recipe);

        $requirements = [];
        $canUnlock = count($recipe) > 0;

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                $canUnlock = false;
                $requirements[] = [
                    'placeholder' => true,
                    'nom' => $requirement->getPlaceholderNom() ?? 'Carte à venir',
                    'needed' => $requirement->getQuantityRequired(),
                    'owned' => 0,
                    'ok' => false,
                ];
                continue;
            }

            $needed = $requirement->getQuantityRequired();

            $ownedQuantity = $ownedById[$requiredCard->getId()] ?? 0;
            $ok = $ownedQuantity >= $needed;

            // Toutes les alternatives sont renvoyées (pas seulement celle qui valide la
            // ligne) pour que le joueur sache quelles autres cartes sont acceptées.
            $viaAlt = null;
            $alternatives = [];
            foreach ($requirement->getAlternativeCards() as $altCard) {
                $altQuantity = $ownedById[$altCard->getId()] ?? 0;
                $alternatives[] = [
                    'nom' => $altCard->getNom(),
                    'owned' => $altQuantity,
                ];
                if (!$ok && $altQuantity >= $needed) {
                    $ok = true;
                    $viaAlt = $altCard;
                    $ownedQuantity = $altQuantity;
                }
            }

            if (!$ok) {
                $canUnlock = false;
            }

            $requirements[] = [
                'id' => $requiredCard->getId(),
                'nom' => $requiredCard->getNom(),
                'imagePath' => $requiredCard->getImagePath(),
                'needed' => $needed,
                'owned' => $ownedQuantity,
                'ok' => $ok,
                'wishlisted' => $user->getWishlistCardJeus()->contains($requiredCard),
                'viaAlt' => $viaAlt?->getNom(),
                'alternatives' => $alternatives,
            ];
        }

        return $this->json([
            'nom' => $card->getNom(),
            'alreadyOwned' => $alreadyOwned,
            'canUnlock' => $canUnlock && !$alreadyOwned,
            'requirements' => $requirements,
        ]);
    }

    #[Route('/catalogue/jeu/mythic/{id}/unlock', name: 'app_mythic_jeu_unlock', methods: ['POST'])]
    public function jeuUnlock(CardJeu $card, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService, WishlistService $wishlistService, CardAttributionService $cardAttributionService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('mythic-unlock', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $user = $this->getUser();

        $existing = $entityManager->getRepository(UserCardJeu::class)->findOneBy([
            'user' => $user,
            'cardJeu' => $card,
        ]);
        if ($existing !== null && $existing->getQuantity() > 0) {
            return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
        }

        $recipe = $this->loadRecipe($entityManager, CardJeuRequirement::class, $card);
        $ownedById = $this->ownedQuantities($entityManager, $user, UserCardJeu::class, 'cardJeu', $recipe);

        if (count($recipe) === 0) {
            return $this->json(['success' => false, 'message' => 'Aucune combinaison n\'est configurée pour cette carte.'], 400);
        }

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                return $this->json(['success' => false, 'message' => "Cette carte n'est pas encore complètement configurée."], 400);
            }

            $needed = $requirement->getQuantityRequired();

            $satisfied = ($ownedById[$requiredCard->getId()] ?? 0) >= $needed;

            if (!$satisfied) {
                foreach ($requirement->getAlternativeCards() as $altCard) {
                    if (($ownedById[$altCard->getId()] ?? 0) >= $needed) {
                        $satisfied = true;
                        break;
                    }
                }
            }

            if (!$satisfied) {
                return $this->json(['success' => false, 'message' => 'La combinaison n\'est pas complète.'], 400);
            }
        }

        try {
            $cardAttributionService->attribuer($user, $card, 1, true);
        } catch (CarteDejaPossedeeException) {
            return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
        } catch (StockEpuiseException) {
            return $this->json(['success' => false, 'message' => "Cette carte mythique n'est plus disponible."], 400);
        }

        $badgeService->refreshCollectorBadges($user);
        $wishlistService->removeJeuCardFromWishlist($user, $card);

        return $this->json([
            'success' => true,
            'message' => 'Carte mythique débloquée !',
            'pseudo' => $user->getPseudo(),
        ]);
    }

    /**
     * Ingrédients d'une mythique, avec la carte requise et les alternatives chargées dans
     * la même requête (sinon une requête par ingrédient et par alternative).
     *
     * @param class-string $requirementClass
     *
     * @return array<int, CardAnimeRequirement|CardFilmRequirement|CardJeuRequirement>
     */
    private function loadRecipe(EntityManagerInterface $entityManager, string $requirementClass, CardAnime|CardFilm|CardJeu $card): array
    {
        return $entityManager->getRepository($requirementClass)
            ->createQueryBuilder('r')
            ->addSelect('rc', 'alt')
            ->leftJoin('r.requiredCard', 'rc')
            ->leftJoin('r.alternativeCards', 'alt')
            ->where('r.mythicCard = :card')
            ->setParameter('card', $card)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Quantités possédées par le joueur pour toutes les cartes citées dans la recette
     * (requises et alternatives), en une seule requête.
     *
     * @param class-string $userCardClass
     * @param array<int, CardAnimeRequirement|CardFilmRequirement|CardJeuRequirement> $recipe
     *
     * @return array<int, int> quantité indexée par id de carte
     */
    private function ownedQuantities(EntityManagerInterface $entityManager, User $user, string $userCardClass, string $cardProperty, array $recipe): array
    {
        $cardIds = [];
        foreach ($recipe as $requirement) {
            if ($requirement->getRequiredCard() !== null) {
                $cardIds[] = $requirement->getRequiredCard()->getId();
            }
            foreach ($requirement->getAlternativeCards() as $altCard) {
                $cardIds[] = $altCard->getId();
            }
        }

        if ($cardIds === []) {
            return [];
        }

        $rows = $entityManager->getRepository($userCardClass)
            ->createQueryBuilder('uc')
            ->select("IDENTITY(uc.$cardProperty) AS cardId, uc.quantity AS quantity")
            ->where('uc.user = :user')
            ->andWhere("uc.$cardProperty IN (:cardIds)")
            ->setParameter('user', $user)
            ->setParameter('cardIds', array_unique($cardIds))
            ->getQuery()
            ->getArrayResult();

        $quantities = [];
        foreach ($rows as $row) {
            $quantities[(int) $row['cardId']] = (int) $row['quantity'];
        }

        return $quantities;
    }
}
