<?php

namespace App\Repository;

use App\Entity\TradeOffer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TradeOffer>
 */
class TradeOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TradeOffer::class);
    }

    /**
     * Offres en attente reçues par cet utilisateur.
     *
     * @return TradeOffer[]
     */
    public function findPendingReceivedBy(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.recipient = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', TradeOffer::STATUS_PENDING)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Offres en attente envoyées par cet utilisateur.
     *
     * @return TradeOffer[]
     */
    public function findPendingSentBy(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.proposer = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', TradeOffer::STATUS_PENDING)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique (offres résolues) impliquant cet utilisateur.
     *
     * @return TradeOffer[]
     */
    public function findHistoryFor(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('(t.proposer = :user OR t.recipient = :user)')
            ->andWhere('t.status != :status')
            ->setParameter('user', $user)
            ->setParameter('status', TradeOffer::STATUS_PENDING)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Offres encore en attente dans lesquelles cet utilisateur a engagé au moins une carte
     * (peu importe qu'il soit proposer ou recipient).
     *
     * Sert à repérer les offres devenues impossibles après qu'un échange a été accepté.
     *
     * @return TradeOffer[]
     */
    public function findPendingWithItemOwnedBy(User $user, ?TradeOffer $exclude = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.items', 'i')
            ->andWhere('i.owner = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', TradeOffer::STATUS_PENDING)
            ->distinct();

        if ($exclude !== null && $exclude->getId() !== null) {
            $qb->andWhere('t.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function countPendingReceivedBy(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.recipient = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', TradeOffer::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
