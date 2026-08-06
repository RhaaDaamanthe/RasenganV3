<?php

namespace App\Entity;

use App\Repository\TradeOfferItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TradeOfferItemRepository::class)]
class TradeOfferItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TradeOffer $tradeOffer = null;

    // Le joueur qui met cette carte sur la table (proposer OU recipient de l'offre)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    private ?CardAnime $cardAnime = null;

    #[ORM\ManyToOne]
    private ?CardFilm $cardFilm = null;

    #[ORM\Column]
    private ?int $quantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTradeOffer(): ?TradeOffer
    {
        return $this->tradeOffer;
    }

    public function setTradeOffer(?TradeOffer $tradeOffer): static
    {
        $this->tradeOffer = $tradeOffer;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCardAnime(): ?CardAnime
    {
        return $this->cardAnime;
    }

    public function setCardAnime(?CardAnime $cardAnime): static
    {
        $this->cardAnime = $cardAnime;

        return $this;
    }

    public function getCardFilm(): ?CardFilm
    {
        return $this->cardFilm;
    }

    public function setCardFilm(?CardFilm $cardFilm): static
    {
        $this->cardFilm = $cardFilm;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getCard(): CardAnime|CardFilm|null
    {
        return $this->cardAnime ?? $this->cardFilm;
    }
}
