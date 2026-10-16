<?php

namespace App\Enum;

use App\Entity\Anime;
use App\Entity\CardAnime;
use App\Entity\CardAnimeRequirement;
use App\Entity\CardFilm;
use App\Entity\CardFilmRequirement;
use App\Entity\CardJeu;
use App\Entity\CardJeuRequirement;
use App\Entity\Film;
use App\Entity\Jeu;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use Doctrine\Common\Collections\Collection;

/**
 * Les trois familles de cartes (animé, film, jeu vidéo) sont strictement parallèles :
 * cet enum regroupe tout ce qui les distingue (classes, propriétés Doctrine, libellés)
 * pour que le code commun n'ait à être écrit qu'une fois.
 *
 * La valeur est le segment utilisé dans les URLs et les noms de routes (`app_wheel_anime_...`).
 */
enum CardType: string
{
    case Anime = 'anime';
    case Film = 'film';
    case Jeu = 'jeu';

    public static function fromCard(CardAnime|CardFilm|CardJeu $card): self
    {
        return match (true) {
            $card instanceof CardAnime => self::Anime,
            $card instanceof CardFilm => self::Film,
            default => self::Jeu,
        };
    }

    /**
     * @return class-string<CardAnime|CardFilm|CardJeu>
     */
    public function cardClass(): string
    {
        return match ($this) {
            self::Anime => CardAnime::class,
            self::Film => CardFilm::class,
            self::Jeu => CardJeu::class,
        };
    }

    /**
     * @return class-string<UserCardAnime|UserCardFilm|UserCardJeu>
     */
    public function userCardClass(): string
    {
        return match ($this) {
            self::Anime => UserCardAnime::class,
            self::Film => UserCardFilm::class,
            self::Jeu => UserCardJeu::class,
        };
    }

    /**
     * @return class-string<CardAnimeRequirement|CardFilmRequirement|CardJeuRequirement>
     */
    public function requirementClass(): string
    {
        return match ($this) {
            self::Anime => CardAnimeRequirement::class,
            self::Film => CardFilmRequirement::class,
            self::Jeu => CardJeuRequirement::class,
        };
    }

    /**
     * Propriété de UserCard* qui pointe vers la carte (`cardAnime`, `cardFilm`, `cardJeu`).
     */
    public function cardProperty(): string
    {
        return 'card' . ucfirst($this->value);
    }

    /**
     * Propriété de Card* qui pointe vers l'œuvre (`anime`, `film`, `jeu`).
     */
    public function titleProperty(): string
    {
        return $this->value;
    }

    /**
     * Libellé du type affiché dans les notifications Discord.
     */
    public function discordLabel(): string
    {
        return match ($this) {
            self::Anime => 'Anime',
            self::Film => 'Film',
            self::Jeu => 'Jeu vidéo',
        };
    }

    public function titleOf(CardAnime|CardFilm|CardJeu $card): Anime|Film|Jeu|null
    {
        return match ($this) {
            self::Anime => $card->getAnime(),
            self::Film => $card->getFilm(),
            self::Jeu => $card->getJeu(),
        };
    }

    public function cardOf(UserCardAnime|UserCardFilm|UserCardJeu $userCard): CardAnime|CardFilm|CardJeu|null
    {
        return match ($this) {
            self::Anime => $userCard->getCardAnime(),
            self::Film => $userCard->getCardFilm(),
            self::Jeu => $userCard->getCardJeu(),
        };
    }

    public function newUserCard(CardAnime|CardFilm|CardJeu $card): UserCardAnime|UserCardFilm|UserCardJeu
    {
        return match ($this) {
            self::Anime => (new UserCardAnime())->setCardAnime($card),
            self::Film => (new UserCardFilm())->setCardFilm($card),
            self::Jeu => (new UserCardJeu())->setCardJeu($card),
        };
    }

    /**
     * @return Collection<int, CardAnime|CardFilm|CardJeu>
     */
    public function wishlistOf(User $user): Collection
    {
        return match ($this) {
            self::Anime => $user->getWishlistCardAnimes(),
            self::Film => $user->getWishlistCardFilms(),
            self::Jeu => $user->getWishlistCardJeus(),
        };
    }

    /**
     * @return Collection<int, UserCardAnime|UserCardFilm|UserCardJeu>
     */
    public function userCardsOf(User $user): Collection
    {
        return match ($this) {
            self::Anime => $user->getUserCardAnimes(),
            self::Film => $user->getUserCardFilms(),
            self::Jeu => $user->getUserCardJeus(),
        };
    }
}
