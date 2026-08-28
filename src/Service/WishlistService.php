<?php

namespace App\Service;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
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

    public function removeJeuCardFromWishlist(User $user, CardJeu $card): void
    {
        if ($user->getWishlistCardJeus()->contains($card)) {
            $user->removeWishlistCardJeu($card);
            $this->em->flush();
        }
    }

    /**
     * Ids des cartes présentes dans la wishlist d'un joueur, prêts à être testés en Twig.
     *
     * @return array{anime: array<int, bool>, film: array<int, bool>, jeu: array<int, bool>} tableaux indexés par id de carte
     */
    public function getWishlistCardIds(User $user): array
    {
        $ids = ['anime' => [], 'film' => [], 'jeu' => []];

        foreach ($user->getWishlistCardAnimes() as $card) {
            $ids['anime'][$card->getId()] = true;
        }

        foreach ($user->getWishlistCardFilms() as $card) {
            $ids['film'][$card->getId()] = true;
        }

        foreach ($user->getWishlistCardJeus() as $card) {
            $ids['jeu'][$card->getId()] = true;
        }

        return $ids;
    }

    /**
     * Croise les collections de deux joueurs avec leurs wishlists respectives.
     *
     * - "iCanOffer" : mes doublons (quantité > 1) que l'autre a mis en wishlist.
     * - "iCanGet"   : les cartes que l'autre possède et que j'ai mises en wishlist.
     *
     * @return array{iCanOffer: array<int, array{card: CardAnime|CardFilm|CardJeu, type: string, quantity: int}>, iCanGet: array<int, array{card: CardAnime|CardFilm|CardJeu, type: string, quantity: int}>}
     */
    public function findTradeSuggestions(User $me, User $other): array
    {
        return [
            'iCanOffer' => array_merge(
                $this->matchAnime($me, $other, true),
                $this->matchFilm($me, $other, true),
                $this->matchJeu($me, $other, true)
            ),
            'iCanGet' => array_merge(
                $this->matchAnime($other, $me, false),
                $this->matchFilm($other, $me, false),
                $this->matchJeu($other, $me, false)
            ),
        ];
    }

    /**
     * Cartes anime possédées par $owner et présentes dans la wishlist de $wisher.
     *
     * @param bool $duplicatesOnly n'a de sens que pour ses propres cartes : on ne propose
     *                             que les doublons, pas la dernière copie d'une carte
     *
     * @return array<int, array{card: CardAnime, type: string, quantity: int}>
     */
    private function matchAnime(User $owner, User $wisher, bool $duplicatesOnly): array
    {
        $qb = $this->em->getRepository(UserCardAnime::class)->createQueryBuilder('uc')
            ->join('uc.cardAnime', 'c')
            ->join('c.wishlistedByUsers', 'w')
            ->join('c.rarity', 'r')
            ->andWhere('uc.user = :owner')
            ->andWhere('w = :wisher')
            ->setParameter('owner', $owner)
            ->setParameter('wisher', $wisher)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('c.nom', 'ASC');

        if ($duplicatesOnly) {
            $qb->andWhere('uc.quantity > 1');
        }

        return array_map(
            fn (UserCardAnime $uc) => ['card' => $uc->getCardAnime(), 'type' => 'anime', 'quantity' => $uc->getQuantity()],
            $qb->getQuery()->getResult()
        );
    }

    /**
     * @return array<int, array{card: CardFilm, type: string, quantity: int}>
     */
    private function matchFilm(User $owner, User $wisher, bool $duplicatesOnly): array
    {
        $qb = $this->em->getRepository(UserCardFilm::class)->createQueryBuilder('uc')
            ->join('uc.cardFilm', 'c')
            ->join('c.wishlistedByUsers', 'w')
            ->join('c.rarity', 'r')
            ->andWhere('uc.user = :owner')
            ->andWhere('w = :wisher')
            ->setParameter('owner', $owner)
            ->setParameter('wisher', $wisher)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('c.nom', 'ASC');

        if ($duplicatesOnly) {
            $qb->andWhere('uc.quantity > 1');
        }

        return array_map(
            fn (UserCardFilm $uc) => ['card' => $uc->getCardFilm(), 'type' => 'film', 'quantity' => $uc->getQuantity()],
            $qb->getQuery()->getResult()
        );
    }

    /**
     * @return array<int, array{card: CardJeu, type: string, quantity: int}>
     */
    private function matchJeu(User $owner, User $wisher, bool $duplicatesOnly): array
    {
        $qb = $this->em->getRepository(UserCardJeu::class)->createQueryBuilder('uc')
            ->join('uc.cardJeu', 'c')
            ->join('c.wishlistedByUsers', 'w')
            ->join('c.rarity', 'r')
            ->andWhere('uc.user = :owner')
            ->andWhere('w = :wisher')
            ->setParameter('owner', $owner)
            ->setParameter('wisher', $wisher)
            ->orderBy('r.id', 'DESC')
            ->addOrderBy('c.nom', 'ASC');

        if ($duplicatesOnly) {
            $qb->andWhere('uc.quantity > 1');
        }

        return array_map(
            fn (UserCardJeu $uc) => ['card' => $uc->getCardJeu(), 'type' => 'jeu', 'quantity' => $uc->getQuantity()],
            $qb->getQuery()->getResult()
        );
    }
}
