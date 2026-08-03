<?php

namespace App\Service;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class WishlistService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function removeAnimeCardFromWishlist(User $user, CardAnime $card): void
    {
        if ($user->getWishlistCardAnimes()->contains($card)) {
            $user->removeWishlistCardAnime($card);
            $this->em->flush();
        }
    }

    public function removeFilmCardFromWishlist(User $user, CardFilm $card): void
    {
        if ($user->getWishlistCardFilms()->contains($card)) {
            $user->removeWishlistCardFilm($card);
            $this->em->flush();
        }
    }
}
