<?php

namespace App\Entity;

use App\Repository\CardFilmRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardFilmRepository::class)]
class CardFilm
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'cardFilms')]
    private ?Rarities $rarity = null;

    #[ORM\ManyToOne(inversedBy: 'cardFilms')]
    private ?Film $film = null; // <= Correction ici !

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $quantity = null;

    /**
     * @var Collection<int, UserCardFilm>
     */
    #[ORM\OneToMany(targetEntity: UserCardFilm::class, mappedBy: 'cardFilm')]
    private Collection $userCardFilms;

    public function __construct()
    {
        $this->userCardFilms = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getRarity(): ?Rarities
    {
        return $this->rarity;
    }

    public function setRarity(?Rarities $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }

    public function getFilm(): ?Film
    {
        return $this->film;
    }

    public function setFilm(?Film $film): static
    {
        $this->film = $film;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    /**
     * @return Collection<int, UserCardFilm>
     */
    public function getUserCardFilms(): Collection
    {
        return $this->userCardFilms;
    }

    public function addUserCardFilm(UserCardFilm $userCardFilm): static
    {
        if (!$this->userCardFilms->contains($userCardFilm)) {
            $this->userCardFilms->add($userCardFilm);
            $userCardFilm->setCardFilm($this);
        }

        return $this;
    }

    public function removeUserCardFilm(UserCardFilm $userCardFilm): static
    {
        if ($this->userCardFilms->removeElement($userCardFilm)) {
            // set the owning side to null (unless already changed)
            if ($userCardFilm->getCardFilm() === $this) {
                $userCardFilm->setCardFilm(null);
            }
        }

        return $this;
    }
}