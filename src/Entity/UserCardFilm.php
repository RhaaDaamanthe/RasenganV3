<?php

namespace App\Entity;

use App\Repository\UserCardFilmRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCardFilmRepository::class)]
class UserCardFilm
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userCardFilms')]
    private ?user $user = null;

    #[ORM\ManyToOne(inversedBy: 'userCardFilms')]
    private ?CardFilm $cardFilm = null;

    #[ORM\Column]
    private ?int $quantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?user
    {
        return $this->user;
    }

    public function setUser(?user $user): static
    {
        $this->user = $user;

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
}
