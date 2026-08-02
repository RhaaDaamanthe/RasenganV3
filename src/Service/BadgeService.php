<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BadgeService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function refreshCollectorBadges(User $user): void
    {
        $count = $user->getTotalCardsCount();
        $tiers = $this->em->getRepository(Badge::class)->findCollectorBadgesOrderedByLevel();

        $target = null;
        foreach ($tiers as $badge) {
            if ($count >= $badge->getObjective()) {
                $target = $badge;
            }
        }

        $current = array_filter(
            $user->getBadges()->toArray(),
            fn (Badge $b) => $b->getType() === 'collectionneur'
        );

        if (count($current) === 1 && $target !== null && reset($current)->getId() === $target->getId()) {
            return;
        }
        if (count($current) === 0 && $target === null) {
            return;
        }

        foreach ($current as $badge) {
            $user->removeBadge($badge);
        }
        if ($target !== null) {
            $user->addBadge($target);
        }

        $this->em->flush();
    }

    public function refreshSeniorityBadges(User $user): void
    {
        $registeredSince = $user->getDateCreation();
        if ($registeredSince === null) {
            return;
        }

        $daysSinceRegistration = $registeredSince->diff(new \DateTimeImmutable())->days;
        $tiers = $this->em->getRepository(Badge::class)->findSeniorityBadgesOrderedByLevel();

        $target = null;
        foreach ($tiers as $badge) {
            if ($daysSinceRegistration >= $badge->getObjective()) {
                $target = $badge;
            }
        }

        $current = array_filter(
            $user->getBadges()->toArray(),
            fn (Badge $b) => $b->getType() === 'anciennete'
        );

        if (count($current) === 1 && $target !== null && reset($current)->getId() === $target->getId()) {
            return;
        }
        if (count($current) === 0 && $target === null) {
            return;
        }

        foreach ($current as $badge) {
            $user->removeBadge($badge);
        }
        if ($target !== null) {
            $user->addBadge($target);
        }

        $this->em->flush();
    }

    public function getBadgeShowcase(User $user): array
    {
        $this->refreshCollectorBadges($user);
        $this->refreshSeniorityBadges($user);

        $badgeRepo = $this->em->getRepository(Badge::class);

        $collector = $this->buildTierShowcase(
            $badgeRepo->findCollectorBadgesOrderedByLevel(),
            $user->getTotalCardsCount()
        );

        $registeredSince = $user->getDateCreation();
        $daysSinceRegistration = $registeredSince !== null
            ? $registeredSince->diff(new \DateTimeImmutable())->days
            : 0;
        $seniority = $this->buildTierShowcase(
            $badgeRepo->findSeniorityBadgesOrderedByLevel(),
            $daysSinceRegistration
        );

        $ownedIds = array_map(fn (Badge $b) => $b->getId(), $user->getBadges()->toArray());
        $events = array_map(fn (Badge $b) => [
            'badge' => $b,
            'unlocked' => in_array($b->getId(), $ownedIds, true),
        ], $badgeRepo->findEventBadgesOrderedByPosition());

        return [
            'collectionneur' => $collector,
            'anciennete' => $seniority,
            'event' => $events,
        ];
    }

    private function buildTierShowcase(array $tiers, int $metric): array
    {
        $result = [];
        $previousObjective = 0;
        $highestUnlockedIndex = null;
        $progressAssigned = false;

        foreach ($tiers as $index => $badge) {
            $unlocked = $metric >= $badge->getObjective();
            if ($unlocked) {
                $highestUnlockedIndex = $index;
            }

            $progress = null;
            if (!$unlocked && !$progressAssigned) {
                $span = max(1, $badge->getObjective() - $previousObjective);
                $progress = (int) min(100, max(0, floor(($metric - $previousObjective) / $span * 100)));
                $progressAssigned = true;
            }

            $result[] = [
                'badge' => $badge,
                'unlocked' => $unlocked,
                'current' => false,
                'progress' => $progress,
            ];

            $previousObjective = $badge->getObjective();
        }

        if ($highestUnlockedIndex !== null) {
            $result[$highestUnlockedIndex]['current'] = true;
        }

        return ['metric' => $metric, 'tiers' => $result];
    }
}
