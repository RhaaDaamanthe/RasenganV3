<?php

namespace App\Entity;

use App\Repository\TradeOfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TradeOfferRepository::class)]
class TradeOffer
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COUNTERED = 'countered';

    /**
     * Offre devenue impossible : une des cartes engagées a été échangée entre-temps
     * via une autre offre acceptée. Annulée automatiquement par le système.
     */
    public const STATUS_INVALIDATED = 'invalidated';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $proposer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne(targetEntity: TradeOffer::class, inversedBy: 'counterOffers')]
    private ?TradeOffer $parentOffer = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: TradeOffer::class, mappedBy: 'parentOffer')]
    private Collection $counterOffers;

    /**
     * @var Collection<int, TradeOfferItem>
     */
    #[ORM\OneToMany(targetEntity: TradeOfferItem::class, mappedBy: 'tradeOffer', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->counterOffers = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProposer(): ?User
    {
        return $this->proposer;
    }

    public function setProposer(?User $proposer): static
    {
        $this->proposer = $proposer;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getParentOffer(): ?self
    {
        return $this->parentOffer;
    }

    public function setParentOffer(?self $parentOffer): static
    {
        $this->parentOffer = $parentOffer;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getCounterOffers(): Collection
    {
        return $this->counterOffers;
    }

    /**
     * @return Collection<int, TradeOfferItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(TradeOfferItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setTradeOffer($this);
        }

        return $this;
    }

    public function removeItem(TradeOfferItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getTradeOffer() === $this) {
                $item->setTradeOffer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TradeOfferItem>
     */
    public function getItemsOfferedBy(User $user): Collection
    {
        return $this->items->filter(fn (TradeOfferItem $item) => $item->getOwner() === $user);
    }
}
