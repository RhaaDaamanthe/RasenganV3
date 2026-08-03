<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WishlistController extends AbstractController
{
    #[Route('/catalogue/anime/wishlist/{id}/toggle', name: 'app_wishlist_toggle_anime', methods: ['POST'])]
    public function toggleAnime(CardAnime $card, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wishlist-toggle', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $user = $this->getUser();
        $alreadyWishlisted = $user->getWishlistCardAnimes()->contains($card);

        if (!$alreadyWishlisted) {
            $owned = $entityManager->getRepository(UserCardAnime::class)->findOneBy([
                'user' => $user,
                'cardAnime' => $card,
            ]);
            if ($owned !== null && $owned->getQuantity() > 0) {
                return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
            }

            $user->addWishlistCardAnime($card);
            $wishlisted = true;
        } else {
            $user->removeWishlistCardAnime($card);
            $wishlisted = false;
        }

        $entityManager->flush();

        return $this->json(['success' => true, 'wishlisted' => $wishlisted]);
    }

    #[Route('/catalogue/film/wishlist/{id}/toggle', name: 'app_wishlist_toggle_film', methods: ['POST'])]
    public function toggleFilm(CardFilm $card, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('wishlist-toggle', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        $user = $this->getUser();
        $alreadyWishlisted = $user->getWishlistCardFilms()->contains($card);

        if (!$alreadyWishlisted) {
            $owned = $entityManager->getRepository(UserCardFilm::class)->findOneBy([
                'user' => $user,
                'cardFilm' => $card,
            ]);
            if ($owned !== null && $owned->getQuantity() > 0) {
                return $this->json(['success' => false, 'message' => 'Vous possédez déjà cette carte.'], 400);
            }

            $user->addWishlistCardFilm($card);
            $wishlisted = true;
        } else {
            $user->removeWishlistCardFilm($card);
            $wishlisted = false;
        }

        $entityManager->flush();

        return $this->json(['success' => true, 'wishlisted' => $wishlisted]);
    }
}
