<?php

namespace App\Entity;

use App\Repository\CardJeuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardJeuRepository::class)]
class CardJeu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'cardJeus')]
    private ?Rarities $rarity = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    private ?Jeu $jeu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $quantity = null;

    /**
     * @var Collection<int, UserCardJeu>
     */
    #[ORM\OneToMany(targetEntity: UserCardJeu::class, mappedBy: 'cardJeu')]
    private Collection $userCardJeus;

    /**
     * @var Collection<int, CardJeuRequirement>
     */
    #[ORM\OneToMany(targetEntity: CardJeuRequirement::class, mappedBy: 'mythicCard', orphanRemoval: true)]
    private Collection $requirements;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'wishlistCardJeus')]
    private Collection $wishlistedByUsers;

    public function __construct()
    {
        $this->userCardJeus = new ArrayCollection();
        $this->requirements = new ArrayCollection();
        $this->wishlistedByUsers = new ArrayCollection();
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

    public function getJeu(): ?Jeu
    {
        return $this->jeu;
    }

    public function setJeu(?Jeu $jeu): static
    {
        $this->jeu = $jeu;

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
     * @return Collection<int, UserCardJeu>
     */
    public function getUserCardJeus(): Collection
    {
        return $this->userCardJeus;
    }

    public function addUserCardJeu(UserCardJeu $userCardJeu): static
    {
        if (!$this->userCardJeus->contains($userCardJeu)) {
            $this->userCardJeus->add($userCardJeu);
            $userCardJeu->setCardJeu($this);
        }

        return $this;
    }

    public function removeUserCardJeu(UserCardJeu $userCardJeu): static
    {
        if ($this->userCardJeus->removeElement($userCardJeu)) {
            // set the owning side to null (unless already changed)
            if ($userCardJeu->getCardJeu() === $this) {
                $userCardJeu->setCardJeu(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CardJeuRequirement>
     */
    public function getRequirements(): Collection
    {
        return $this->requirements;
    }

    public function addRequirement(CardJeuRequirement $requirement): static
    {
        if (!$this->requirements->contains($requirement)) {
            $this->requirements->add($requirement);
            $requirement->setMythicCard($this);
        }

        return $this;
    }

    public function removeRequirement(CardJeuRequirement $requirement): static
    {
        if ($this->requirements->removeElement($requirement)) {
            if ($requirement->getMythicCard() === $this) {
                $requirement->setMythicCard(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getWishlistedByUsers(): Collection
    {
        return $this->wishlistedByUsers;
    }
}
