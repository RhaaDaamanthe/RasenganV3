<?php

namespace App\Service;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use App\Enum\CardType;
use App\Exception\CarteDejaPossedeeException;
use App\Exception\StockEpuiseException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seul point d'entrée pour donner des exemplaires d'une carte à un joueur (roue,
 * attribution manuelle, mythiques) : contrôle du stock sous verrou, création ou
 * incrément du UserCard*, puis badges, wishlist et notification Discord.
 */
class CardAttributionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BadgeService $badgeService,
        private readonly WishlistService $wishlistService,
        private readonly DiscordNotifier $discordNotifier,
    ) {
    }

    /**
     * @param bool $unParJoueur     refuse l'attribution si le joueur possède déjà la carte (mythiques)
     * @param bool $notifierDiscord poste le drop sur Discord (les déblocages de mythiques ne le font pas)
     *
     * @throws StockEpuiseException       si le stock restant est inférieur à $quantity
     * @throws CarteDejaPossedeeException si $unParJoueur et que le joueur a déjà la carte
     */
    public function attribuer(
        User $user,
        CardAnime|CardFilm|CardJeu $card,
        int $quantity = 1,
        bool $unParJoueur = false,
        bool $notifierDiscord = true,
    ): UserCardAnime|UserCardFilm|UserCardJeu {
        $userCard = $this->attribuerSousVerrou($user, $card, $quantity, $unParJoueur);

        // Effets de bord après le commit : ils ont leur propre flush et ne doivent pas
        // pouvoir annuler l'attribution elle-même.
        $this->badgeService->refreshCollectorBadges($user);

        if ($notifierDiscord) {
            $this->discordNotifier->notifyDrop(
                $user,
                $card->getNom(),
                CardType::fromCard($card)->titleOf($card)?->getNom() ?? '',
                $card->getRarity()?->getLibelle() ?? '',
                CardType::fromCard($card)->discordLabel(),
                $card->getImagePath(),
            );
        }

        $this->wishlistService->removeCardFromWishlist($user, $card);

        return $userCard;
    }

    private function attribuerSousVerrou(User $user, CardAnime|CardFilm|CardJeu $card, int $quantity, bool $unParJoueur): UserCardAnime|UserCardFilm|UserCardJeu
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La quantité doit être supérieure à 0.');
        }

        $type = CardType::fromCard($card);

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

            $userCard = $this->entityManager->getRepository($type->userCardClass())
                ->findOneBy(['user' => $user, $type->cardProperty() => $card]);
            if ($userCard !== null) {
                // L'entité a pu être chargée avant le verrou : on relit la quantité committée.
                $this->entityManager->refresh($userCard);
            }

            if ($unParJoueur && $userCard !== null && $userCard->getQuantity() > 0) {
                throw new CarteDejaPossedeeException();
            }

            $restant = $card->getQuantity() - $this->countDistributed($type, $card);
            if ($restant < $quantity) {
                throw new StockEpuiseException(max(0, $restant));
            }

            if ($userCard !== null) {
                $userCard->setQuantity($userCard->getQuantity() + $quantity);
            } else {
                $userCard = $type->newUserCard($card);
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

    private function countDistributed(CardType $type, CardAnime|CardFilm|CardJeu $card): int
    {
        return (int) $this->entityManager->getRepository($type->userCardClass())
            ->createQueryBuilder('uc')
            ->select('SUM(uc.quantity)')
            ->where(sprintf('uc.%s = :card', $type->cardProperty()))
            ->setParameter('card', $card)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
