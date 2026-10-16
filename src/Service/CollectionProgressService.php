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
    /**
     * Abréviation affichée pour chaque rareté, indexée par le slug du libellé.
     */
    private const RARITY_SHORT_LABELS = [
        'commune' => 'C',
        'rare' => 'R',
        'epique' => 'É',
        'legendaire' => 'L',
        'mythique' => 'M',
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<string, array<int, array{id: int, name: string, owned: int, total: int, percent: float, imagePath: ?string, imageOwned: bool, rarities: array<int, array{slug: string, label: string, short: string, owned: int, total: int}>}>>
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
     * Progression par œuvre, triée de la plus fournie à la moins fournie.
     *
     * La vignette est la carte la plus rare possédée de l'œuvre ; à défaut, la
     * première carte de l'œuvre (affichée grisée).
     */
    private function buildProgress(
        User $user,
        string $titleClass,
        string $cardClass,
        string $userCardClass,
        string $titleProperty,
        string $userCardProperty,
    ): array {
        $cards = $this->entityManager->createQueryBuilder()
            ->select(
                'card.id AS id',
                'card.imagePath AS imagePath',
                'title.id AS titleId',
                'title.nom AS titleName',
                'rarity.id AS rarityId',
                'rarity.libelle AS rarityLabel',
            )
            ->from($cardClass, 'card')
            ->join('card.'.$titleProperty, 'title')
            ->leftJoin('card.rarity', 'rarity')
            ->orderBy('title.nom', 'ASC')
            ->addOrderBy('rarity.id', 'ASC')
            ->addOrderBy('card.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $ownedRows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(ownedCard.'.$userCardProperty.') AS cardId')
            ->from($userCardClass, 'ownedCard')
            ->where('ownedCard.user = :user')
            ->andWhere('ownedCard.quantity > 0')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $ownedIds = array_flip(array_map('intval', array_column($ownedRows, 'cardId')));

        $titles = [];
        foreach ($cards as $card) {
            $titleId = (int) $card['titleId'];
            $isOwned = isset($ownedIds[(int) $card['id']]);
            $rarityId = $card['rarityId'] !== null ? (int) $card['rarityId'] : null;

            $titles[$titleId] ??= [
                'id' => $titleId,
                'name' => $card['titleName'],
                'owned' => 0,
                'total' => 0,
                'imagePath' => $card['imagePath'],
                'imageOwned' => false,
                'imageRarity' => null,
                'rarities' => [],
            ];
            $title = &$titles[$titleId];

            ++$title['total'];
            if ($isOwned) {
                ++$title['owned'];
                if ($card['imagePath'] && ($title['imageRarity'] === null || $rarityId > $title['imageRarity'])) {
                    $title['imagePath'] = $card['imagePath'];
                    $title['imageOwned'] = true;
                    $title['imageRarity'] = $rarityId ?? 0;
                }
            } elseif (!$title['imageOwned'] && !$title['imagePath'] && $card['imagePath']) {
                $title['imagePath'] = $card['imagePath'];
            }

            if ($rarityId !== null) {
                $slug = $this->slugify((string) $card['rarityLabel']);
                $title['rarities'][$rarityId] ??= [
                    'slug' => $slug,
                    'label' => $card['rarityLabel'],
                    'short' => self::RARITY_SHORT_LABELS[$slug] ?? mb_strtoupper(mb_substr($slug, 0, 1)),
                    'owned' => 0,
                    'total' => 0,
                ];
                ++$title['rarities'][$rarityId]['total'];
                if ($isOwned) {
                    ++$title['rarities'][$rarityId]['owned'];
                }
            }
            unset($title);
        }

        $progress = array_map(static function (array $title): array {
            unset($title['imageRarity']);
            ksort($title['rarities']);
            $title['rarities'] = array_values($title['rarities']);
            $title['percent'] = $title['total'] > 0 ? round($title['owned'] * 100 / $title['total'], 1) : 0.0;

            return $title;
        }, array_values($titles));

        usort($progress, static fn (array $a, array $b): int => [$b['owned'], $b['percent'], $a['name']] <=> [$a['owned'], $a['percent'], $b['name']]);

        return $progress;
    }

    /**
     * « Épiques » → « epique », « Legendaires » → « legendaire » (mêmes slugs que les rarity-pill).
     */
    private function slugify(string $label): string
    {
        $ascii = strtr(mb_strtolower(trim($label)), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e']);

        return rtrim($ascii, 's');
    }
}
