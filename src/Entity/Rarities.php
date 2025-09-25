<?php

namespace App\Entity;

use App\Repository\RaritiesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RaritiesRepository::class)]
class Rarities
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, CardAnime>
     */
    #[ORM\OneToMany(targetEntity: CardAnime::class, mappedBy: 'rarity')]
    private Collection $cards;

    /**
     * @var Collection<int, CardFilm>
     */
    #[ORM\OneToMany(targetEntity: CardFilm::class, mappedBy: 'rarity')]
    private Collection $cardFilms;

    public function __construct()
    {
        $this->cards = new ArrayCollection();
        $this->cardFilms = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

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
            $card->setRarity($this);
        }

        return $this;
    }

    public function removeCard(CardAnime $card): static
    {
        if ($this->cards->removeElement($card)) {
            // set the owning side to null (unless already changed)
            if ($card->getRarity() === $this) {
                $card->setRarity(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CardFilm>
     */
    public function getCardFilms(): Collection
    {
        return $this->cardFilms;
    }

    public function addCardFilm(CardFilm $cardFilm): static
    {
        if (!$this->cardFilms->contains($cardFilm)) {
            $this->cardFilms->add($cardFilm);
            $cardFilm->setRarity($this);
        }

        return $this;
    }

    public function removeCardFilm(CardFilm $cardFilm): static
    {
        if ($this->cardFilms->removeElement($cardFilm)) {
            // set the owning side to null (unless already changed)
            if ($cardFilm->getRarity() === $this) {
                $cardFilm->setRarity(null);
            }
        }

        return $this;
    }
}