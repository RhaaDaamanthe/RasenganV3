<?php

namespace App\Entity;

use App\Repository\UserCardAnimeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCardAnimeRepository::class)]
class UserCardAnime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userCardAnimes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userCardAnimes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CardAnime $cardAnime = null;

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

    public function getCardAnime(): ?CardAnime
    {
        return $this->cardAnime;
    }

    public function setCardAnime(?CardAnime $cardAnime): static
    {
        $this->cardAnime = $cardAnime;

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
