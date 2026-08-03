<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Service\BadgeService;
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

        $requirements = [];
        $canUnlock = $card->getRequirements()->count() > 0;

        foreach ($card->getRequirements() as $requirement) {
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

            $owned = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
                'user' => $user,
                'cardAnime' => $requiredCard,
            ]);
            $ownedQuantity = $owned?->getQuantity() ?? 0;
            $ok = $ownedQuantity >= $needed;

            $viaAlt = null;
            if (!$ok) {
                foreach ($requirement->getAlternativeCards() as $altCard) {
                    $altOwned = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
                        'user' => $user,
                        'cardAnime' => $altCard,
                    ]);
                    $altQuantity = $altOwned?->getQuantity() ?? 0;
                    if ($altQuantity >= $needed) {
                        $ok = true;
                        $viaAlt = $altCard;
                        $ownedQuantity = $altQuantity;
                        break;
                    }
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
    public function animeUnlock(CardAnime $card, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService, WishlistService $wishlistService): JsonResponse
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

        if ($card->getRequirements()->count() === 0) {
            return $this->json(['success' => false, 'message' => 'Aucune combinaison n\'est configurée pour cette carte.'], 400);
        }

        foreach ($card->getRequirements() as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                return $this->json(['success' => false, 'message' => "Cette carte n'est pas encore complètement configurée."], 400);
            }

            $needed = $requirement->getQuantityRequired();

            $owned = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
                'user' => $user,
                'cardAnime' => $requiredCard,
            ]);
            $satisfied = ($owned?->getQuantity() ?? 0) >= $needed;

            if (!$satisfied) {
                foreach ($requirement->getAlternativeCards() as $altCard) {
                    $altOwned = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
                        'user' => $user,
                        'cardAnime' => $altCard,
                    ]);
                    if (($altOwned?->getQuantity() ?? 0) >= $needed) {
                        $satisfied = true;
                        break;
                    }
                }
            }

            if (!$satisfied) {
                return $this->json(['success' => false, 'message' => 'La combinaison n\'est pas complète.'], 400);
            }
        }

        $totalDistributed = $entityManager->getRepository(UserCardAnime::class)
            ->createQueryBuilder('uca')
            ->select('SUM(uca.quantity)')
            ->where('uca.cardAnime = :card')
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalDistributed >= $card->getQuantity()) {
            return $this->json(['success' => false, 'message' => 'Cette carte mythique n\'est plus disponible.'], 400);
        }

        if ($existing !== null) {
            $existing->setQuantity($existing->getQuantity() + 1);
        } else {
            $existing = new UserCardAnime();
            $existing->setUser($user);
            $existing->setCardAnime($card);
            $existing->setQuantity(1);
            $entityManager->persist($existing);
        }
        $existing->setObtainedAt(new \DateTimeImmutable());

        $entityManager->flush();
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

        $requirements = [];
        $canUnlock = $card->getRequirements()->count() > 0;

        foreach ($card->getRequirements() as $requirement) {
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

            $owned = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
                'user' => $user,
                'cardFilm' => $requiredCard,
            ]);
            $ownedQuantity = $owned?->getQuantity() ?? 0;
            $ok = $ownedQuantity >= $needed;

            $viaAlt = null;
            if (!$ok) {
                foreach ($requirement->getAlternativeCards() as $altCard) {
                    $altOwned = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
                        'user' => $user,
                        'cardFilm' => $altCard,
                    ]);
                    $altQuantity = $altOwned?->getQuantity() ?? 0;
                    if ($altQuantity >= $needed) {
                        $ok = true;
                        $viaAlt = $altCard;
                        $ownedQuantity = $altQuantity;
                        break;
                    }
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
    public function filmUnlock(CardFilm $card, Request $request, EntityManagerInterface $entityManager, BadgeService $badgeService, WishlistService $wishlistService): JsonResponse
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

        if ($card->getRequirements()->count() === 0) {
            return $this->json(['success' => false, 'message' => 'Aucune combinaison n\'est configurée pour cette carte.'], 400);
        }

        foreach ($card->getRequirements() as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                return $this->json(['success' => false, 'message' => "Cette carte n'est pas encore complètement configurée."], 400);
            }

            $needed = $requirement->getQuantityRequired();

            $owned = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
                'user' => $user,
                'cardFilm' => $requiredCard,
            ]);
            $satisfied = ($owned?->getQuantity() ?? 0) >= $needed;

            if (!$satisfied) {
                foreach ($requirement->getAlternativeCards() as $altCard) {
                    $altOwned = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
                        'user' => $user,
                        'cardFilm' => $altCard,
                    ]);
                    if (($altOwned?->getQuantity() ?? 0) >= $needed) {
                        $satisfied = true;
                        break;
                    }
                }
            }

            if (!$satisfied) {
                return $this->json(['success' => false, 'message' => 'La combinaison n\'est pas complète.'], 400);
            }
        }

        $totalDistributed = $entityManager->getRepository(UserCardFilm::class)
            ->createQueryBuilder('ucf')
            ->select('SUM(ucf.quantity)')
            ->where('ucf.cardFilm = :card')
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalDistributed >= $card->getQuantity()) {
            return $this->json(['success' => false, 'message' => 'Cette carte mythique n\'est plus disponible.'], 400);
        }

        if ($existing !== null) {
            $existing->setQuantity($existing->getQuantity() + 1);
        } else {
            $existing = new UserCardFilm();
            $existing->setUser($user);
            $existing->setCardFilm($card);
            $existing->setQuantity(1);
            $entityManager->persist($existing);
        }
        $existing->setObtainedAt(new \DateTimeImmutable());

        $entityManager->flush();
        $badgeService->refreshCollectorBadges($user);
        $wishlistService->removeFilmCardFromWishlist($user, $card);

        return $this->json([
            'success' => true,
            'message' => 'Carte mythique débloquée !',
            'pseudo' => $user->getPseudo(),
        ]);
    }
}
