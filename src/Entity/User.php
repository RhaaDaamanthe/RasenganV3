<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25, unique: true)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $imageCollection = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $titreCollection = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private ?bool $isAdmin = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];
    
    /**
     * @var Collection<int, UserCardAnime>
     */
    #[ORM\OneToMany(targetEntity: UserCardAnime::class, mappedBy: 'user')]
    private Collection $userCardAnimes;

    /**
     * @var Collection<int, UserCardFilm>
     */
    #[ORM\OneToMany(targetEntity: UserCardFilm::class, mappedBy: 'user')]
    private Collection $userCardFilms;

    // Ajout du constructeur
    public function __construct()
    {
        // Initialise la date de création au moment de la création de l'objet
        $this->dateCreation = new \DateTimeImmutable();
        // Définit le statut d'administrateur à false par défaut
        $this->isAdmin = false;
        $this->userCardAnimes = new ArrayCollection();
        $this->userCardFilms = new ArrayCollection();
    }

    // -----------------------
    // Getters & setters
    // -----------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getImageCollection(): ?string
    {
        return $this->imageCollection;
    }

    public function setImageCollection(?string $imageCollection): static
    {
        $this->imageCollection = $imageCollection;
        return $this;
    }

    public function getTitreCollection(): ?string
    {
        return $this->titreCollection;
    }

    public function setTitreCollection(?string $titreCollection): static
    {
        $this->titreCollection = $titreCollection;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function isAdmin(): ?bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;
        return $this;
    }
    
    // -----------------------
    // Security methods
    // -----------------------

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // tout utilisateur a au minimum ROLE_USER
        $roles[] = 'ROLE_USER';

        // si admin -> ROLE_ADMIN
        if ($this->isAdmin) {
            $roles[] = 'ROLE_ADMIN';
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Sert à effacer des données sensibles temporaires (ex: plainPassword)
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
            $userCardAnime->setUser($this);
        }

        return $this;
    }

    public function removeUserCardAnime(UserCardAnime $userCardAnime): static
    {
        if ($this->userCardAnimes->removeElement($userCardAnime)) {
            // set the owning side to null (unless already changed)
            if ($userCardAnime->getUser() === $this) {
                $userCardAnime->setUser(null);
            }
        }

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
            $userCardFilm->setUser($this);
        }

        return $this;
    }

    public function removeUserCardFilm(UserCardFilm $userCardFilm): static
    {
        if ($this->userCardFilms->removeElement($userCardFilm)) {
            // set the owning side to null (unless already changed)
            if ($userCardFilm->getUser() === $this) {
                $userCardFilm->setUser(null);
            }
        }

        return $this;
    }
}