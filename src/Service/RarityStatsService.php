<?php

namespace App\Service;

use App\Controller\WheelController;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use Doctrine\ORM\EntityManagerInterface;

class RarityStatsService
{
    /**
     * Écart (en points de %) qui correspond à une barre pleine — au-delà, elle est plafonnée.
     */
    private const MAX_BAR_DELTA = 25.0;

    private const RARITY_SLUGS = [
        1 => 'commune',
        2 => 'rare',
        3 => 'epique',
        4 => 'legendaire',
    ];

    private const RARITY_LABELS = [
        1 => 'Communes',
        2 => 'Rares',
        3 => 'Épiques',
        4 => 'Légendaires',
    ];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getRarityBreakdown(User $user): array
    {
        $owned = $this->countOwnedByRarity($user);

        $total = 0;
        foreach (WheelController::RARITY_WEIGHTS as $rarityId => $expectedPercent) {
            $total += $owned[$rarityId] ?? 0;
        }

        $breakdown = [];
        foreach (WheelController::RARITY_WEIGHTS as $rarityId => $expectedPercent) {
            $qty = $owned[$rarityId] ?? 0;
            $actualPercent = $total > 0 ? ($qty / $total * 100) : 0.0;
            $delta = $actualPercent - $expectedPercent;

            $breakdown[] = [
                'slug' => self::RARITY_SLUGS[$rarityId],
                'label' => self::RARITY_LABELS[$rarityId],
                'owned' => $qty,
                'actualPercent' => $actualPercent,
                'expectedPercent' => (float) $expectedPercent,
                'delta' => $delta,
                'barWidth' => min(50.0, abs($delta) / self::MAX_BAR_DELTA * 50.0),
            ];
        }

        return [
            'total' => $total,
            'rarities' => $breakdown,
        ];
    }

    private function countOwnedByRarity(User $user): array
    {
        $counts = [];

        $animeRows = $this->em->createQueryBuilder()
            ->select('r.id as rarityId, SUM(uca.quantity) as qty')
            ->from(UserCardAnime::class, 'uca')
            ->join('uca.cardAnime', 'ca')
            ->join('ca.rarity', 'r')
            ->where('uca.user = :user')
            ->setParameter('user', $user)
            ->groupBy('r.id')
            ->getQuery()
            ->getResult();

        foreach ($animeRows as $row) {
            $rarityId = (int) $row['rarityId'];
            $counts[$rarityId] = ($counts[$rarityId] ?? 0) + (int) $row['qty'];
        }

        $filmRows = $this->em->createQueryBuilder()
            ->select('r.id as rarityId, SUM(ucf.quantity) as qty')
            ->from(UserCardFilm::class, 'ucf')
            ->join('ucf.cardFilm', 'cf')
            ->join('cf.rarity', 'r')
            ->where('ucf.user = :user')
            ->setParameter('user', $user)
            ->groupBy('r.id')
            ->getQuery()
            ->getResult();

        foreach ($filmRows as $row) {
            $rarityId = (int) $row['rarityId'];
            $counts[$rarityId] = ($counts[$rarityId] ?? 0) + (int) $row['qty'];
        }

        return $counts;
    }
}
