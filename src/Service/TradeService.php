<?php

namespace App\Service;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\TradeOffer;
use App\Entity\TradeOfferItem;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Repository\TradeOfferRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class TradeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TradeOfferRepository $tradeOfferRepository,
        private readonly BadgeService $badgeService,
        private readonly WishlistService $wishlistService,
        private readonly DiscordNotifier $discordNotifier,
    ) {
    }

    /**
     * Crée une nouvelle offre d'échange.
     *
     * @param array<int, array{owner: User, type: string, cardId: int, quantity: int}> $itemsData
     *
     * @throws \InvalidArgumentException si l'offre est invalide (cartes non possédées en quantité suffisante, offre vide, etc.)
     */
    public function proposeTrade(User $proposer, User $recipient, array $itemsData, ?TradeOffer $parentOffer = null): TradeOffer
    {
        if ($proposer === $recipient) {
            throw new \InvalidArgumentException("Impossible de proposer un échange à soi-même.");
        }

        if (empty($itemsData)) {
            throw new \InvalidArgumentException("L'offre doit contenir au moins une carte.");
        }

        $offer = new TradeOffer();
        $offer->setProposer($proposer);
        $offer->setRecipient($recipient);
        $offer->setParentOffer($parentOffer);

        $hasProposerItem = false;
        $hasRecipientItem = false;

        // On regroupe d'abord les lignes portant sur la même carte du même joueur :
        // sans ça, deux lignes "anime:42:1" passeraient la validation alors que le
        // joueur ne possède qu'un seul exemplaire.
        foreach ($this->aggregateItems($itemsData) as $data) {
            $owner = $data['owner'];
            if ($owner !== $proposer && $owner !== $recipient) {
                throw new \InvalidArgumentException('Le propriétaire de la carte doit être un des deux joueurs.');
            }

            $item = new TradeOfferItem();
            $item->setOwner($owner);
            $item->setQuantity($data['quantity']);

            $card = $this->findCard($data['type'], $data['cardId']);
            $this->assertOwnsCard($owner, $card, $data['quantity']);

            if ($card instanceof CardAnime) {
                $item->setCardAnime($card);
            } else {
                $item->setCardFilm($card);
            }

            if ($owner === $proposer) {
                $hasProposerItem = true;
            } else {
                $hasRecipientItem = true;
            }

            $offer->addItem($item);
        }

        if (!$hasProposerItem || !$hasRecipientItem) {
            throw new \InvalidArgumentException("L'échange doit contenir au moins une carte de chaque joueur.");
        }

        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        $this->discordNotifier->notifyTradeProposed($offer);

        return $offer;
    }

    public function accept(TradeOffer $offer, User $currentUser): void
    {
        $this->assertPending($offer);

        if ($offer->getRecipient() !== $currentUser) {
            throw new \InvalidArgumentException("Seul le destinataire de l'offre peut l'accepter.");
        }

        $proposer = $offer->getProposer();
        $recipient = $offer->getRecipient();

        // Tout le transfert doit être atomique : si une seule ligne échoue, personne ne
        // doit perdre de carte. Les verrous pessimistes empêchent en plus deux échanges
        // concurrents de dépenser la même carte.
        $this->entityManager->wrapInTransaction(function () use ($offer, $proposer, $recipient): void {
            $this->lockUserCards($offer);

            // Revérification après verrouillage : les cartes ont pu être échangées
            // ou retirées entre la création de l'offre et son acceptation.
            foreach ($offer->getItems() as $item) {
                $this->assertOwnsCard($item->getOwner(), $item->getCard(), $item->getQuantity());
            }

            foreach ($offer->getItems() as $item) {
                $receiver = $item->getOwner() === $proposer ? $recipient : $proposer;
                $this->transferCard($item->getOwner(), $receiver, $item->getCard(), $item->getQuantity());
            }

            $offer->setStatus(TradeOffer::STATUS_ACCEPTED);
            $offer->setResolvedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            // Les autres offres en attente qui engageaient ces mêmes cartes peuvent
            // être devenues impossibles : on les annule au lieu de les laisser échouer
            // plus tard avec un message incompréhensible.
            $this->invalidateImpossibleOffers($offer, $proposer, $recipient);
        });

        // Effets de bord post-échange : ils ont leur propre flush et ne doivent pas
        // pouvoir faire échouer (ni rollback) le transfert lui-même.
        $this->cleanWishlistsAfterTrade($offer);
        $this->badgeService->refreshCollectorBadges($proposer);
        $this->badgeService->refreshCollectorBadges($recipient);

        $this->discordNotifier->notifyTradeAccepted($offer);
    }

    public function decline(TradeOffer $offer, User $currentUser): void
    {
        $this->assertPending($offer);

        if ($offer->getRecipient() !== $currentUser) {
            throw new \InvalidArgumentException("Seul le destinataire de l'offre peut la refuser.");
        }

        $offer->setStatus(TradeOffer::STATUS_DECLINED);
        $offer->setResolvedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function cancel(TradeOffer $offer, User $currentUser): void
    {
        $this->assertPending($offer);

        if ($offer->getProposer() !== $currentUser) {
            throw new \InvalidArgumentException("Seul l'auteur de l'offre peut l'annuler.");
        }

        $offer->setStatus(TradeOffer::STATUS_CANCELLED);
        $offer->setResolvedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * Le destinataire refuse l'offre initiale et en propose une nouvelle en retour (rôles inversés).
     *
     * @param array<int, array{owner: User, type: string, cardId: int, quantity: int}> $itemsData
     */
    public function counter(TradeOffer $offer, User $currentUser, array $itemsData): TradeOffer
    {
        $this->assertPending($offer);

        if ($offer->getRecipient() !== $currentUser) {
            throw new \InvalidArgumentException("Seul le destinataire de l'offre peut faire une contre-offre.");
        }

        $offer->setStatus(TradeOffer::STATUS_COUNTERED);
        $offer->setResolvedAt(new \DateTimeImmutable());

        return $this->proposeTrade($offer->getRecipient(), $offer->getProposer(), $itemsData, $offer);
    }

    /**
     * Regroupe les lignes portant sur la même carte du même joueur en additionnant les quantités.
     *
     * @param array<int, array{owner: User, type: string, cardId: int, quantity: int}> $itemsData
     *
     * @return array<string, array{owner: User, type: string, cardId: int, quantity: int}>
     */
    private function aggregateItems(array $itemsData): array
    {
        $aggregated = [];

        foreach ($itemsData as $data) {
            $quantity = (int) $data['quantity'];
            if ($quantity < 1) {
                throw new \InvalidArgumentException('La quantité doit être supérieure à 0.');
            }

            if (!in_array($data['type'], ['anime', 'film'], true)) {
                throw new \InvalidArgumentException('Type de carte invalide.');
            }

            $key = spl_object_id($data['owner']) . '|' . $data['type'] . '|' . (int) $data['cardId'];

            if (isset($aggregated[$key])) {
                $aggregated[$key]['quantity'] += $quantity;
                continue;
            }

            $aggregated[$key] = [
                'owner' => $data['owner'],
                'type' => $data['type'],
                'cardId' => (int) $data['cardId'],
                'quantity' => $quantity,
            ];
        }

        return $aggregated;
    }

    private function findCard(string $type, int $cardId): CardAnime|CardFilm
    {
        $card = $type === 'anime'
            ? $this->entityManager->getRepository(CardAnime::class)->find($cardId)
            : $this->entityManager->getRepository(CardFilm::class)->find($cardId);

        if (!$card) {
            throw new \InvalidArgumentException($type === 'anime' ? 'Carte anime introuvable.' : 'Carte film introuvable.');
        }

        return $card;
    }

    /**
     * Pose un verrou d'écriture sur les lignes UserCard* engagées dans l'offre.
     *
     * Les verrous sont pris dans un ordre déterministe (classe puis id) pour que deux
     * échanges concurrents portant sur les mêmes cartes ne se bloquent pas mutuellement.
     */
    private function lockUserCards(TradeOffer $offer): void
    {
        $userCards = [];

        foreach ($offer->getItems() as $item) {
            foreach ([$offer->getProposer(), $offer->getRecipient()] as $participant) {
                $userCard = $this->findUserCard($participant, $item->getCard());
                if ($userCard !== null) {
                    $userCards[$userCard::class . '#' . $userCard->getId()] = $userCard;
                }
            }
        }

        ksort($userCards);

        foreach ($userCards as $userCard) {
            // SELECT ... FOR UPDATE : verrouille la ligne ET relit la quantité committée
            // par une éventuelle transaction concurrente qui vient de se terminer.
            $this->entityManager->refresh($userCard, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /**
     * Annule les autres offres en attente devenues irréalisables après cet échange.
     */
    private function invalidateImpossibleOffers(TradeOffer $acceptedOffer, User ...$users): void
    {
        $checked = [];

        foreach ($users as $user) {
            foreach ($this->tradeOfferRepository->findPendingWithItemOwnedBy($user, $acceptedOffer) as $offer) {
                if (isset($checked[$offer->getId()])) {
                    continue;
                }
                $checked[$offer->getId()] = true;

                foreach ($offer->getItems() as $item) {
                    $userCard = $this->findUserCard($item->getOwner(), $item->getCard());

                    if (!$userCard || $userCard->getQuantity() < $item->getQuantity()) {
                        $offer->setStatus(TradeOffer::STATUS_INVALIDATED);
                        $offer->setResolvedAt(new \DateTimeImmutable());
                        break;
                    }
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Une carte reçue par échange n'a plus rien à faire dans la wishlist de celui qui la reçoit.
     */
    private function cleanWishlistsAfterTrade(TradeOffer $offer): void
    {
        foreach ($offer->getItems() as $item) {
            $receiver = $item->getOwner() === $offer->getProposer() ? $offer->getRecipient() : $offer->getProposer();
            $card = $item->getCard();

            if ($card instanceof CardAnime) {
                $this->wishlistService->removeAnimeCardFromWishlist($receiver, $card);
            } else {
                $this->wishlistService->removeFilmCardFromWishlist($receiver, $card);
            }
        }
    }

    private function assertPending(TradeOffer $offer): void
    {
        if (!$offer->isPending()) {
            throw new \InvalidArgumentException('Cette offre a déjà été traitée.');
        }
    }

    private function assertOwnsCard(User $owner, CardAnime|CardFilm $card, int $quantity): void
    {
        $userCard = $this->findUserCard($owner, $card);

        if (!$userCard || $userCard->getQuantity() < $quantity) {
            throw new \InvalidArgumentException(sprintf(
                '%s ne possède pas %dx "%s".',
                $owner->getPseudo(),
                $quantity,
                $card->getNom()
            ));
        }
    }

    private function findUserCard(User $owner, CardAnime|CardFilm $card): UserCardAnime|UserCardFilm|null
    {
        if ($card instanceof CardAnime) {
            return $this->entityManager->getRepository(UserCardAnime::class)
                ->findOneBy(['user' => $owner, 'cardAnime' => $card]);
        }

        return $this->entityManager->getRepository(UserCardFilm::class)
            ->findOneBy(['user' => $owner, 'cardFilm' => $card]);
    }

    private function transferCard(User $from, User $to, CardAnime|CardFilm $card, int $quantity): void
    {
        $fromUserCard = $this->findUserCard($from, $card);

        if ($fromUserCard->getQuantity() <= $quantity) {
            $this->entityManager->remove($fromUserCard);
        } else {
            $fromUserCard->setQuantity($fromUserCard->getQuantity() - $quantity);
        }

        $toUserCard = $this->findUserCard($to, $card);

        if ($toUserCard) {
            $toUserCard->setQuantity($toUserCard->getQuantity() + $quantity);
        } else {
            $toUserCard = $card instanceof CardAnime ? new UserCardAnime() : new UserCardFilm();
            $toUserCard->setUser($to);
            if ($card instanceof CardAnime) {
                $toUserCard->setCardAnime($card);
            } else {
                $toUserCard->setCardFilm($card);
            }
            $toUserCard->setQuantity($quantity);
            $toUserCard->setObtainedAt(new \DateTimeImmutable());
            $this->entityManager->persist($toUserCard);
        }
    }
}
