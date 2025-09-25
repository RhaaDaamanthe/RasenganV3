<?php

namespace App\Entity;

use App\Repository\FilmRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilmRepository::class)]
class Film
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    /**
     * @var Collection<int, CardFilm>
     */
    #[ORM\OneToMany(targetEntity: CardFilm::class, mappedBy: 'film')]
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
     * @return Collection<int, CardFilm>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(CardFilm $card): static
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setFilm($this);
        }

        return $this;
    }

    public function removeCard(CardFilm $card): static
    {
        if ($this->cards->removeElement($card)) {
            // set the owning side to null (unless already changed)
            if ($card->getFilm() === $this) {
                $card->setFilm(null);
            }
        }

        return $this;
    }
}