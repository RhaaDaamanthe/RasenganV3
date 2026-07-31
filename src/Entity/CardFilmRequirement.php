<?php

namespace App\Entity;

use App\Repository\CardFilmRequirementRepository;
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
    #[ORM\JoinColumn(nullable: false)]
    private ?CardFilm $requiredCard = null;

    #[ORM\Column]
    private int $quantityRequired = 1;

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

    public function getQuantityRequired(): int
    {
        return $this->quantityRequired;
    }

    public function setQuantityRequired(int $quantityRequired): static
    {
        $this->quantityRequired = $quantityRequired;

        return $this;
    }
}
