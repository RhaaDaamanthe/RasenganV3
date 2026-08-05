<?php

namespace App\Service;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\TradeOffer;
use App\Entity\TradeOfferItem;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use Doctrine\ORM\EntityManagerInterface;

class TradeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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

        foreach ($itemsData as $data) {
            $owner = $data['owner'];
            if ($owner !== $proposer && $owner !== $recipient) {
                throw new \InvalidArgumentException('Le propriétaire de la carte doit être un des deux joueurs.');
            }

            $quantity = (int) $data['quantity'];
            if ($quantity < 1) {
                throw new \InvalidArgumentException('La quantité doit être supérieure à 0.');
            }

            $item = new TradeOfferItem();
            $item->setOwner($owner);
            $item->setQuantity($quantity);

            if ($data['type'] === 'anime') {
                $card = $this->entityManager->getRepository(CardAnime::class)->find($data['cardId']);
                if (!$card) {
                    throw new \InvalidArgumentException('Carte anime introuvable.');
                }
                $this->assertOwnsCard($owner, $card, $quantity);
                $item->setCardAnime($card);
            } elseif ($data['type'] === 'film') {
                $card = $this->entityManager->getRepository(CardFilm::class)->find($data['cardId']);
                if (!$card) {
                    throw new \InvalidArgumentException('Carte film introuvable.');
                }
                $this->assertOwnsCard($owner, $card, $quantity);
                $item->setCardFilm($card);
            } else {
                throw new \InvalidArgumentException('Type de carte invalide.');
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

        return $offer;
    }

    public function accept(TradeOffer $offer, User $currentUser): void
    {
        $this->assertPending($offer);

        if ($offer->getRecipient() !== $currentUser) {
            throw new \InvalidArgumentException("Seul le destinataire de l'offre peut l'accepter.");
        }

        // On revérifie que chaque joueur possède toujours bien les cartes proposées
        // (elles ont pu être échangées/retirées entre-temps).
        foreach ($offer->getItems() as $item) {
            $card = $item->getCard();
            $this->assertOwnsCard($item->getOwner(), $card, $item->getQuantity());
        }

        foreach ($offer->getItems() as $item) {
            $recipientOfCard = $item->getOwner() === $offer->getProposer() ? $offer->getRecipient() : $offer->getProposer();
            $this->transferCard($item->getOwner(), $recipientOfCard, $item->getCard(), $item->getQuantity());
        }

        $offer->setStatus(TradeOffer::STATUS_ACCEPTED);
        $offer->setResolvedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
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
