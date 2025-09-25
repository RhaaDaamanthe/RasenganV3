<?php

namespace App\Entity;

use App\Repository\CardAnimeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardAnimeRepository::class)]
class CardAnime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rarities $rarity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Anime $anime = null;

    /**
     * @var Collection<int, UserCardAnime>
     */
    #[ORM\OneToMany(targetEntity: UserCardAnime::class, mappedBy: 'cardAnime')]
    private Collection $userCardAnimes;

    #[ORM\Column]
    private ?int $quantity = null;

    public function __construct()
    {
        $this->userCardAnimes = new ArrayCollection();
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

    public function getAnime(): ?Anime
    {
        return $this->anime;
    }

    public function setAnime(?Anime $anime): static
    {
        $this->anime = $anime;

        return $this;
    }

    /**
     * @return Collection<int, UserCardAnime>
     */
    public function getUserCardAnimes(): Collection
    {
        return $this->userCardAnimes;
    }

    public function addUserCardAnime(UserCardAnime $userCardAnime): static
    {
        if (!$this->userCardAnimes->contains($userCardAnime)) {
            $this->userCardAnimes->add($userCardAnime);
            $userCardAnime->setCardAnime($this);
        }

        return $this;
    }

    public function removeUserCardAnime(UserCardAnime $userCardAnime): static
    {
        if ($this->userCardAnimes->removeElement($userCardAnime)) {
            // set the owning side to null (unless already changed)
            if ($userCardAnime->getCardAnime() === $this) {
                $userCardAnime->setCardAnime(null);
            }
        }

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
     * @return string[]
     */
    public function getOwnersList(): array
    {
        $owners = [];
        foreach ($this->getUserCardAnimes() as $userCardAnime) {
            $owners[] = $userCardAnime->getUser()->getNom();
        }

        return $owners;
    }
}