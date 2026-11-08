<?php

namespace App\Service;

use App\Entity\TradeOffer;
use App\Entity\TradeOfferItem;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression définitive d'un compte joueur.
 *
 * Les tables d'échanges et de possession n'ont pas de ON DELETE CASCADE vers
 * l'utilisateur : on les vide d'abord, dans la même transaction. Les wishlists et
 * les badges (ManyToMany côté User) sont nettoyés par Doctrine, la vitrine par la
 * contrainte ON DELETE CASCADE.
 */
class UserDeletionService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function supprimer(User $user): void
    {
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use ($user): void {
            // Toutes les offres où le joueur est partie prenante, quel que soit leur statut.
            $offerIds = $em->createQueryBuilder()
                ->select('o.id')
                ->from(TradeOffer::class, 'o')
                ->where('o.proposer = :user OR o.recipient = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleColumnResult();

            if ($offerIds !== []) {
                // Détacher les contre-offres qui pointeraient vers une offre supprimée.
                $em->createQuery('UPDATE ' . TradeOffer::class . ' o SET o.parentOffer = NULL WHERE o.parentOffer IN (:ids)')
                    ->setParameter('ids', $offerIds)
                    ->execute();

                $em->createQuery('DELETE FROM ' . TradeOfferItem::class . ' i WHERE i.tradeOffer IN (:ids)')
                    ->setParameter('ids', $offerIds)
                    ->execute();

                $em->createQuery('DELETE FROM ' . TradeOffer::class . ' o WHERE o.id IN (:ids)')
                    ->setParameter('ids', $offerIds)
                    ->execute();
            }

            // Sécurité : aucun item ne doit rester rattaché au joueur.
            $em->createQuery('DELETE FROM ' . TradeOfferItem::class . ' i WHERE i.owner = :user')
                ->setParameter('user', $user)
                ->execute();

            // Cartes possédées : elles retournent dans le stock disponible.
            foreach ([UserCardAnime::class, UserCardFilm::class, UserCardJeu::class] as $userCardClass) {
                $em->createQuery('DELETE FROM ' . $userCardClass . ' uc WHERE uc.user = :user')
                    ->setParameter('user', $user)
                    ->execute();
            }

            $em->remove($user);
            $em->flush();
        });
    }
}
