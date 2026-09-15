<?php

namespace App\Service;

use App\Entity\Anime;
use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\Film;
use App\Entity\Jeu;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use Doctrine\ORM\EntityManagerInterface;

class CollectionProgressService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<string, array<int, array{id: int, name: string, owned: int, total: int, percent: float}>>
     */
    public function getProgressByTitle(User $user): array
    {
        return [
            'anime' => $this->buildProgress($user, Anime::class, CardAnime::class, UserCardAnime::class, 'anime', 'cardAnime'),
            'film' => $this->buildProgress($user, Film::class, CardFilm::class, UserCardFilm::class, 'film', 'cardFilm'),
            'jeu' => $this->buildProgress($user, Jeu::class, CardJeu::class, UserCardJeu::class, 'jeu', 'cardJeu'),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, owned: int, total: int, percent: float}>
     */
    private function buildProgress(
        User $user,
        string $titleClass,
        string $cardClass,
        string $userCardClass,
        string $titleProperty,
        string $userCardProperty,
    ): array {
        $totals = $this->entityManager->createQueryBuilder()
            ->select('title.id AS id', 'title.nom AS name', 'COUNT(card.id) AS total')
            ->from($titleClass, 'title')
            ->leftJoin($cardClass, 'card', 'WITH', sprintf('card.%s = title', $titleProperty))
            ->groupBy('title.id', 'title.nom')
            ->having('COUNT(card.id) > 0')
            ->orderBy('title.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $ownedRows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(card.'.$titleProperty.') AS titleId', 'COUNT(DISTINCT card.id) AS owned')
            ->from($userCardClass, 'ownedCard')
            ->join('ownedCard.'.$userCardProperty, 'card')
            ->where('ownedCard.user = :user')
            ->andWhere('ownedCard.quantity > 0')
            ->setParameter('user', $user)
            ->groupBy('card.'.$titleProperty)
            ->getQuery()
            ->getArrayResult();

        $ownedByTitle = array_column($ownedRows, 'owned', 'titleId');

        return array_map(static function (array $row) use ($ownedByTitle): array {
            $total = (int) $row['total'];
            $owned = (int) ($ownedByTitle[$row['id']] ?? 0);

            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'owned' => $owned,
                'total' => $total,
                'percent' => $total > 0 ? round($owned * 100 / $total, 1) : 0.0,
            ];
        }, $totals);
    }
}
