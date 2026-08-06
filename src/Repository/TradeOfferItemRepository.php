<?php

namespace App\Repository;

use App\Entity\TradeOfferItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TradeOfferItem>
 */
class TradeOfferItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TradeOfferItem::class);
    }
}
