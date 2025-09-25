<?php

namespace App\Entity;

use App\Repository\AnimeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnimeRepository::class)]
class Anime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    /**
     * @var Collection<int, CardAnime>
     */
    #[ORM\OneToMany(targetEntity: CardAnime::class, mappedBy: 'anime')]
    private Collection $cards;

    public function __construct()
    {
        $this->cards = new ArrayCollection();
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

    /**
     * @return Collection<int, CardAnime>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(CardAnime $card): static
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setAnime($this);
        }

        return $this;
    }

    public function removeCard(CardAnime $card): static
    {
        if ($this->cards->removeElement($card)) {
            // set the owning side to null (unless already changed)
            if ($card->getAnime() === $this) {
                $card->setAnime(null);
            }
        }

        return $this;
    }
}
