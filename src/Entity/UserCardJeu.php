<?php

namespace App\Entity;

use App\Repository\UserCardJeuRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCardJeuRepository::class)]
class UserCardJeu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userCardJeus')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userCardJeus')]
    private ?CardJeu $cardJeu = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $obtainedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCardJeu(): ?CardJeu
    {
        return $this->cardJeu;
    }

    public function setCardJeu(?CardJeu $cardJeu): static
    {
        $this->cardJeu = $cardJeu;

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

    public function getObtainedAt(): ?\DateTimeImmutable
    {
        return $this->obtainedAt;
    }

    public function setObtainedAt(\DateTimeImmutable $obtainedAt): static
    {
        $this->obtainedAt = $obtainedAt;

        return $this;
    }
}
