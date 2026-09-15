<?php

namespace App\Entity;

use App\Repository\ShowcaseCardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShowcaseCardRepository::class)]
#[ORM\Table(name: 'user_showcase_card')]
#[ORM\UniqueConstraint(name: 'UNIQ_SHOWCASE_USER_POSITION', columns: ['user_id', 'position'])]
class ShowcaseCard
{
    public const DEFAULT_CAPACITY = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'showcaseCards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private int $position = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?CardAnime $cardAnime = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?CardFilm $cardFilm = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?CardJeu $cardJeu = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCardAnime(): ?CardAnime
    {
        return $this->cardAnime;
    }

    public function getCardFilm(): ?CardFilm
    {
        return $this->cardFilm;
    }

    public function getCardJeu(): ?CardJeu
    {
        return $this->cardJeu;
    }

    public function setCard(CardAnime|CardFilm|CardJeu $card): static
    {
        $this->cardAnime = $card instanceof CardAnime ? $card : null;
        $this->cardFilm = $card instanceof CardFilm ? $card : null;
        $this->cardJeu = $card instanceof CardJeu ? $card : null;

        return $this;
    }

    public function getCard(): CardAnime|CardFilm|CardJeu|null
    {
        return $this->cardAnime ?? $this->cardFilm ?? $this->cardJeu;
    }

    public function getType(): string
    {
        return match (true) {
            $this->cardAnime !== null => 'anime',
            $this->cardFilm !== null => 'film',
            $this->cardJeu !== null => 'jeu',
            default => '',
        };
    }
}
