<?php

namespace App\Service;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use App\Exception\CarteDejaPossedeeException;
use App\Exception\StockEpuiseException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Donne des exemplaires d'une carte à un joueur sans jamais dépasser son tirage total.
 *
 * Utilisé par la roue, l'attribution manuelle et le déblocage des mythiques. Les effets
 * de bord (badges, wishlist, Discord) restent à la charge de l'appelant.
 */
class CardAttributionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param bool $unParJoueur refuse l'attribution si le joueur possède déjà la carte (mythiques)
     *
     * @throws StockEpuiseException       si le stock restant est inférieur à $quantity
     * @throws CarteDejaPossedeeException si $unParJoueur et que le joueur a déjà la carte
     */
    public function attribuer(User $user, CardAnime|CardFilm|CardJeu $card, int $quantity = 1, bool $unParJoueur = false): UserCardAnime|UserCardFilm|UserCardJeu
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La quantité doit être supérieure à 0.');
        }

        // Transaction gérée à la main plutôt que wrapInTransaction() : ce dernier ferme
        // l'EntityManager à la moindre exception, or un refus (stock épuisé...) est un cas
        // normal après lequel la page doit encore pouvoir s'afficher. Le refus intervient
        // avant toute écriture, un simple rollback suffit donc.
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            // SELECT ... FOR UPDATE sur la carte : deux attributions simultanées de la même
            // carte s'exécutent l'une après l'autre, la seconde voit donc le stock à jour.
            $this->entityManager->lock($card, LockMode::PESSIMISTIC_WRITE);

            $userCard = $this->findUserCard($user, $card);
            if ($userCard !== null) {
                // L'entité a pu être chargée avant le verrou : on relit la quantité committée.
                $this->entityManager->refresh($userCard);
            }

            if ($unParJoueur && $userCard !== null && $userCard->getQuantity() > 0) {
                throw new CarteDejaPossedeeException();
            }

            $restant = $card->getQuantity() - $this->countDistributed($card);
            if ($restant < $quantity) {
                throw new StockEpuiseException(max(0, $restant));
            }

            if ($userCard !== null) {
                $userCard->setQuantity($userCard->getQuantity() + $quantity);
            } else {
                $userCard = $this->createUserCard($card);
                $userCard->setUser($user);
                $userCard->setQuantity($quantity);
                $this->entityManager->persist($userCard);
            }
            $userCard->setObtainedAt(new \DateTimeImmutable());

            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        return $userCard;
    }

    private function countDistributed(CardAnime|CardFilm|CardJeu $card): int
    {
        [$class, $property] = $this->mapping($card);

        return (int) $this->entityManager->getRepository($class)
            ->createQueryBuilder('uc')
            ->select('SUM(uc.quantity)')
            ->where("uc.$property = :card")
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function findUserCard(User $user, CardAnime|CardFilm|CardJeu $card): UserCardAnime|UserCardFilm|UserCardJeu|null
    {
        [$class, $property] = $this->mapping($card);

        return $this->entityManager->getRepository($class)->findOneBy(['user' => $user, $property => $card]);
    }

    private function createUserCard(CardAnime|CardFilm|CardJeu $card): UserCardAnime|UserCardFilm|UserCardJeu
    {
        if ($card instanceof CardAnime) {
            return (new UserCardAnime())->setCardAnime($card);
        }

        if ($card instanceof CardFilm) {
            return (new UserCardFilm())->setCardFilm($card);
        }

        return (new UserCardJeu())->setCardJeu($card);
    }

    /**
     * @return array{0: class-string, 1: string}
     */
    private function mapping(CardAnime|CardFilm|CardJeu $card): array
    {
        return match (true) {
            $card instanceof CardAnime => [UserCardAnime::class, 'cardAnime'],
            $card instanceof CardFilm => [UserCardFilm::class, 'cardFilm'],
            default => [UserCardJeu::class, 'cardJeu'],
        };
    }
}
