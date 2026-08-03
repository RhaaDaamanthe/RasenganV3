<?php

namespace App\Entity;

use App\Repository\CardFilmRequirementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardFilmRequirementRepository::class)]
class CardFilmRequirement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'requirements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CardFilm $mythicCard = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?CardFilm $requiredCard = null;

    #[ORM\Column(nullable: true)]
    private ?string $placeholderNom = null;

    #[ORM\Column]
    private int $quantityRequired = 1;

    /**
     * Cartes alternatives acceptées pour valider cet ingrédient (ex: une carte
     * event du même personnage) en plus de la carte principale requise.
     *
     * @var Collection<int, CardFilm>
     */
    #[ORM\ManyToMany(targetEntity: CardFilm::class)]
    #[ORM\JoinTable(name: 'card_film_requirement_alternative')]
    private Collection $alternativeCards;

    public function __construct()
    {
        $this->alternativeCards = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMythicCard(): ?CardFilm
    {
        return $this->mythicCard;
    }

    public function setMythicCard(?CardFilm $mythicCard): static
    {
        $this->mythicCard = $mythicCard;

        return $this;
    }

    public function getRequiredCard(): ?CardFilm
    {
        return $this->requiredCard;
    }

    public function setRequiredCard(?CardFilm $requiredCard): static
    {
        $this->requiredCard = $requiredCard;

        return $this;
    }

    public function getPlaceholderNom(): ?string
    {
        return $this->placeholderNom;
    }

    public function setPlaceholderNom(?string $placeholderNom): static
    {
        $this->placeholderNom = $placeholderNom;

        return $this;
    }

    public function getQuantityRequired(): int
    {
        return $this->quantityRequired;
    }

    public function setQuantityRequired(int $quantityRequired): static
    {
        $this->quantityRequired = $quantityRequired;

        return $this;
    }

    /**
     * @return Collection<int, CardFilm>
     */
    public function getAlternativeCards(): Collection
    {
        return $this->alternativeCards;
    }

    public function addAlternativeCard(CardFilm $card): static
    {
        if (!$this->alternativeCards->contains($card)) {
            $this->alternativeCards->add($card);
        }

        return $this;
    }

    public function removeAlternativeCard(CardFilm $card): static
    {
        $this->alternativeCards->removeElement($card);

        return $this;
    }

    public function clearAlternativeCards(): static
    {
        $this->alternativeCards->clear();

        return $this;
    }
}
