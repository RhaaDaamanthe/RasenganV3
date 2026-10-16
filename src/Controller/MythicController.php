<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\User;
use App\Exception\DeblocageImpossibleException;
use App\Service\MythicRecipeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MythicController extends AbstractController
{
    public function __construct(
        private readonly MythicRecipeService $mythicRecipeService,
    ) {
    }

    #[Route('/catalogue/anime/mythic/{id}/status', name: 'app_mythic_anime_status', methods: ['GET'])]
    public function animeStatus(CardAnime $card): JsonResponse
    {
        return $this->status($card);
    }

    #[Route('/catalogue/anime/mythic/{id}/unlock', name: 'app_mythic_anime_unlock', methods: ['POST'])]
    public function animeUnlock(CardAnime $card, Request $request): JsonResponse
    {
        return $this->unlock($card, $request);
    }

    #[Route('/catalogue/film/mythic/{id}/status', name: 'app_mythic_film_status', methods: ['GET'])]
    public function filmStatus(CardFilm $card): JsonResponse
    {
        return $this->status($card);
    }

    #[Route('/catalogue/film/mythic/{id}/unlock', name: 'app_mythic_film_unlock', methods: ['POST'])]
    public function filmUnlock(CardFilm $card, Request $request): JsonResponse
    {
        return $this->unlock($card, $request);
    }

    #[Route('/catalogue/jeu/mythic/{id}/status', name: 'app_mythic_jeu_status', methods: ['GET'])]
    public function jeuStatus(CardJeu $card): JsonResponse
    {
        return $this->status($card);
    }

    #[Route('/catalogue/jeu/mythic/{id}/unlock', name: 'app_mythic_jeu_unlock', methods: ['POST'])]
    public function jeuUnlock(CardJeu $card, Request $request): JsonResponse
    {
        return $this->unlock($card, $request);
    }

    private function status(CardAnime|CardFilm|CardJeu $card): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->mythicRecipeService->getStatus($user, $card));
    }

    private function unlock(CardAnime|CardFilm|CardJeu $card, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('mythic-unlock', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->mythicRecipeService->unlock($user, $card);
        } catch (DeblocageImpossibleException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        return $this->json([
            'success' => true,
            'message' => 'Carte mythique débloquée !',
            'pseudo' => $user->getPseudo(),
        ]);
    }
}
