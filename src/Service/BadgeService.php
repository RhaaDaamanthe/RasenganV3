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
}
