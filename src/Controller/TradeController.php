<?php

namespace App\Controller;

use App\Entity\Rarities;
use App\Entity\TradeOffer;
use App\Entity\User;
use App\Entity\UserCardFilm;
use App\Repository\TradeOfferRepository;
use App\Repository\UserCardAnimeRepository;
use App\Repository\UserRepository;
use App\Service\TradeService;
use App\Service\WishlistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/echanges')]
#[IsGranted('ROLE_USER')]
class TradeController extends AbstractController
{
    #[Route('', name: 'app_trade_list')]
    public function list(TradeOfferRepository $tradeOfferRepository, UserRepository $userRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $otherPlayers = $userRepository->createQueryBuilder('u')
            ->andWhere('u != :me')
            ->setParameter('me', $user)
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('trade/list.html.twig', [
            'received' => $tradeOfferRepository->findPendingReceivedBy($user),
            'sent' => $tradeOfferRepository->findPendingSentBy($user),
            'history' => $tradeOfferRepository->findHistoryFor($user),
            'otherPlayers' => $otherPlayers,
        ]);
    }

    #[Route('/nouveau/{id}', name: 'app_trade_new')]
    public function new(
        User $recipient,
        Request $request,
        UserCardAnimeRepository $userCardAnimeRepository,
        EntityManagerInterface $entityManager,
        TradeService $tradeService,
        WishlistService $wishlistService,
    ): Response {
        /** @var User $proposer */
        $proposer = $this->getUser();

        if ($proposer === $recipient) {
            $this->addFlash('error', "Vous ne pouvez pas vous proposer un échange à vous-même.");

            return $this->redirectToRoute('app_players_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $itemsData = $this->parseItemsFromRequest($request, $proposer, $recipient);
                $offer = $tradeService->proposeTrade($proposer, $recipient, $itemsData);

                $this->addFlash('success', "✅ Offre d'échange envoyée à {$recipient->getPseudo()} !");

                return $this->redirectToRoute('app_trade_show', ['id' => $offer->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('trade/new.html.twig', [
            'recipient' => $recipient,
            'myCardAnimes' => $userCardAnimeRepository->findByUserSorted($proposer),
            'myCardFilms' => $entityManager->getRepository(UserCardFilm::class)->findBy(['user' => $proposer]),
            'theirCardAnimes' => $userCardAnimeRepository->findByUserSorted($recipient),
            'theirCardFilms' => $entityManager->getRepository(UserCardFilm::class)->findBy(['user' => $recipient]),
            'rarities' => $entityManager->getRepository(Rarities::class)->findAll(),
            'myWishlist' => $wishlistService->getWishlistCardIds($proposer),
            'theirWishlist' => $wishlistService->getWishlistCardIds($recipient),
        ]);
    }

    /**
     * Croise les doublons et les wishlists des deux joueurs pour proposer des pistes d'échange.
     */
    #[Route('/suggestions/{id}', name: 'app_trade_suggestions', requirements: ['id' => '\d+'])]
    public function suggestions(User $other, WishlistService $wishlistService): Response
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me === $other) {
            $this->addFlash('error', "Vous ne pouvez pas échanger avec vous-même.");

            return $this->redirectToRoute('app_trade_list');
        }

        return $this->render('trade/suggestions.html.twig', [
            'other' => $other,
            'suggestions' => $wishlistService->findTradeSuggestions($me, $other),
        ]);
    }

    #[Route('/{id}', name: 'app_trade_show', requirements: ['id' => '\d+'])]
    public function show(TradeOffer $offer): Response
    {
        $this->assertParticipant($offer);

        return $this->render('trade/show.html.twig', [
            'offer' => $offer,
            'isRecipient' => $offer->getRecipient() === $this->getUser(),
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_trade_accept', methods: ['POST'])]
    public function accept(TradeOffer $offer, Request $request, TradeService $tradeService): Response
    {
        $this->assertParticipant($offer);
        $this->assertCsrf($request, 'trade_accept', $offer);

        try {
            $tradeService->accept($offer, $this->getUser());
            $this->addFlash('success', '✅ Échange accepté, les cartes ont été échangées !');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_trade_show', ['id' => $offer->getId()]);
    }

    #[Route('/{id}/refuser', name: 'app_trade_decline', methods: ['POST'])]
    public function decline(TradeOffer $offer, Request $request, TradeService $tradeService): Response
    {
        $this->assertParticipant($offer);
        $this->assertCsrf($request, 'trade_decline', $offer);

        try {
            $tradeService->decline($offer, $this->getUser());
            $this->addFlash('success', 'Offre refusée.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_trade_show', ['id' => $offer->getId()]);
    }

    #[Route('/{id}/annuler', name: 'app_trade_cancel', methods: ['POST'])]
    public function cancel(TradeOffer $offer, Request $request, TradeService $tradeService): Response
    {
        $this->assertParticipant($offer);
        $this->assertCsrf($request, 'trade_cancel', $offer);

        try {
            $tradeService->cancel($offer, $this->getUser());
            $this->addFlash('success', 'Offre annulée.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_trade_list');
    }

    #[Route('/{id}/contre-offre', name: 'app_trade_counter')]
    public function counter(
        TradeOffer $offer,
        Request $request,
        UserCardAnimeRepository $userCardAnimeRepository,
        EntityManagerInterface $entityManager,
        TradeService $tradeService,
        WishlistService $wishlistService,
    ): Response {
        $this->assertParticipant($offer);

        /** @var User $me */
        $me = $this->getUser();

        if ($offer->getRecipient() !== $me) {
            $this->addFlash('error', "Seul le destinataire de l'offre peut faire une contre-offre.");

            return $this->redirectToRoute('app_trade_show', ['id' => $offer->getId()]);
        }

        $other = $offer->getProposer();

        if ($request->isMethod('POST')) {
            try {
                $itemsData = $this->parseItemsFromRequest($request, $me, $other, $entityManager);
                $newOffer = $tradeService->counter($offer, $me, $itemsData);

                $this->addFlash('success', "✅ Contre-offre envoyée à {$other->getPseudo()} !");

                return $this->redirectToRoute('app_trade_show', ['id' => $newOffer->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('trade/new.html.twig', [
            'recipient' => $other,
            'counterOf' => $offer,
            'myCardAnimes' => $userCardAnimeRepository->findByUserSorted($me),
            'myCardFilms' => $entityManager->getRepository(UserCardFilm::class)->findBy(['user' => $me]),
            'theirCardAnimes' => $userCardAnimeRepository->findByUserSorted($other),
            'theirCardFilms' => $entityManager->getRepository(UserCardFilm::class)->findBy(['user' => $other]),
            'rarities' => $entityManager->getRepository(Rarities::class)->findAll(),
            'myWishlist' => $wishlistService->getWishlistCardIds($me),
            'theirWishlist' => $wishlistService->getWishlistCardIds($other),
        ]);
    }

    /**
     * Lit les champs "proposer_items[]" et "recipient_items[]" du formulaire,
     * chacun au format "type:cardId:quantity" (ex: "anime:42:2").
     *
     * @return array<int, array{owner: User, type: string, cardId: int, quantity: int}>
     */
    private function parseItemsFromRequest(Request $request, User $proposer, User $recipient): array
    {
        $items = [];

        foreach ($request->request->all('proposer_items') as $raw) {
            $items[] = $this->parseItem($raw, $proposer);
        }

        foreach ($request->request->all('recipient_items') as $raw) {
            $items[] = $this->parseItem($raw, $recipient);
        }

        return array_filter($items);
    }

    private function parseItem(string $raw, User $owner): ?array
    {
        [$type, $cardId, $quantity] = array_pad(explode(':', $raw), 3, null);

        if (!$type || !$cardId || !$quantity) {
            return null;
        }

        return [
            'owner' => $owner,
            'type' => $type,
            'cardId' => (int) $cardId,
            'quantity' => (int) $quantity,
        ];
    }

    private function assertParticipant(TradeOffer $offer): void
    {
        $user = $this->getUser();

        if ($offer->getProposer() !== $user && $offer->getRecipient() !== $user) {
            throw $this->createAccessDeniedException("Vous n'êtes pas concerné par cet échange.");
        }
    }

    /**
     * Sans ce jeton, un formulaire auto-soumis hébergé sur un site tiers pourrait
     * faire accepter/refuser/annuler une offre à l'insu du joueur connecté.
     */
    private function assertCsrf(Request $request, string $intention, TradeOffer $offer): void
    {
        if (!$this->isCsrfTokenValid($intention . $offer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
