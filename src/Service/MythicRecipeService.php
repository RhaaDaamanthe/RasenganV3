<?php

namespace App\Service;

use App\Entity\CardAnime;
use App\Entity\CardAnimeRequirement;
use App\Entity\CardFilm;
use App\Entity\CardFilmRequirement;
use App\Entity\CardJeu;
use App\Entity\CardJeuRequirement;
use App\Entity\User;
use App\Enum\CardType;
use App\Exception\CarteDejaPossedeeException;
use App\Exception\DeblocageImpossibleException;
use App\Exception\StockEpuiseException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Recettes des cartes mythiques : état d'avancement d'un joueur et déblocage.
 *
 * Une recette est une liste d'ingrédients (carte requise + quantité), chacun pouvant être
 * remplacé par une carte alternative. Les cartes requises ne sont pas consommées.
 */
class MythicRecipeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardAttributionService $cardAttributionService,
    ) {
    }

    /**
     * État de la recette pour ce joueur, au format attendu par la fenêtre des mythiques.
     *
     * @return array{nom: ?string, alreadyOwned: bool, canUnlock: bool, requirements: array<int, array<string, mixed>>}
     */
    public function getStatus(User $user, CardAnime|CardFilm|CardJeu $card): array
    {
        $type = CardType::fromCard($card);
        $alreadyOwned = $this->isOwned($user, $card);

        $recipe = $this->loadRecipe($card);
        $ownedById = $this->ownedQuantities($user, $type, $recipe);
        $wishlist = $type->wishlistOf($user);

        $requirements = [];
        $canUnlock = count($recipe) > 0;

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                $canUnlock = false;
                $requirements[] = [
                    'placeholder' => true,
                    'nom' => $requirement->getPlaceholderNom() ?? 'Carte à venir',
                    'needed' => $requirement->getQuantityRequired(),
                    'owned' => 0,
                    'ok' => false,
                ];
                continue;
            }

            $needed = $requirement->getQuantityRequired();
            $ownedQuantity = $ownedById[$requiredCard->getId()] ?? 0;
            $ok = $ownedQuantity >= $needed;

            // Toutes les alternatives sont renvoyées (pas seulement celle qui valide la
            // ligne) pour que le joueur sache quelles autres cartes sont acceptées.
            $viaAlt = null;
            $alternatives = [];
            foreach ($requirement->getAlternativeCards() as $altCard) {
                $altQuantity = $ownedById[$altCard->getId()] ?? 0;
                $alternatives[] = [
                    'nom' => $altCard->getNom(),
                    'owned' => $altQuantity,
                ];
                if (!$ok && $altQuantity >= $needed) {
                    $ok = true;
                    $viaAlt = $altCard;
                    $ownedQuantity = $altQuantity;
                }
            }

            if (!$ok) {
                $canUnlock = false;
            }

            $requirements[] = [
                'id' => $requiredCard->getId(),
                'nom' => $requiredCard->getNom(),
                'imagePath' => $requiredCard->getImagePath(),
                'needed' => $needed,
                'owned' => $ownedQuantity,
                'ok' => $ok,
                'wishlisted' => $wishlist->contains($requiredCard),
                'viaAlt' => $viaAlt?->getNom(),
                'alternatives' => $alternatives,
            ];
        }

        return [
            'nom' => $card->getNom(),
            'alreadyOwned' => $alreadyOwned,
            'canUnlock' => $canUnlock && !$alreadyOwned,
            'requirements' => $requirements,
        ];
    }

    /**
     * Donne la mythique au joueur si sa collection satisfait la recette.
     *
     * @throws DeblocageImpossibleException avec un message destiné au joueur
     */
    public function unlock(User $user, CardAnime|CardFilm|CardJeu $card): void
    {
        if ($this->isOwned($user, $card)) {
            throw new DeblocageImpossibleException('Vous possédez déjà cette carte.');
        }

        $recipe = $this->loadRecipe($card);
        if (count($recipe) === 0) {
            throw new DeblocageImpossibleException("Aucune combinaison n'est configurée pour cette carte.");
        }

        $ownedById = $this->ownedQuantities($user, CardType::fromCard($card), $recipe);

        foreach ($recipe as $requirement) {
            $requiredCard = $requirement->getRequiredCard();

            if ($requiredCard === null) {
                throw new DeblocageImpossibleException("Cette carte n'est pas encore complètement configurée.");
            }

            $needed = $requirement->getQuantityRequired();
            $satisfied = ($ownedById[$requiredCard->getId()] ?? 0) >= $needed;

            foreach ($requirement->getAlternativeCards() as $altCard) {
                if ($satisfied) {
                    break;
                }
                $satisfied = ($ownedById[$altCard->getId()] ?? 0) >= $needed;
            }

            if (!$satisfied) {
                throw new DeblocageImpossibleException("La combinaison n'est pas complète.");
            }
        }

        try {
            $this->cardAttributionService->attribuer($user, $card, 1, unParJoueur: true, notifierDiscord: false);
        } catch (CarteDejaPossedeeException) {
            throw new DeblocageImpossibleException('Vous possédez déjà cette carte.');
        } catch (StockEpuiseException) {
            throw new DeblocageImpossibleException("Cette carte mythique n'est plus disponible.");
        }
    }

    private function isOwned(User $user, CardAnime|CardFilm|CardJeu $card): bool
    {
        $type = CardType::fromCard($card);
        $userCard = $this->entityManager->getRepository($type->userCardClass())
            ->findOneBy(['user' => $user, $type->cardProperty() => $card]);

        return $userCard !== null && $userCard->getQuantity() > 0;
    }

    /**
     * Ingrédients d'une mythique, avec la carte requise et les alternatives chargées dans
     * la même requête (sinon une requête par ingrédient et par alternative).
     *
     * @return array<int, CardAnimeRequirement|CardFilmRequirement|CardJeuRequirement>
     */
    private function loadRecipe(CardAnime|CardFilm|CardJeu $card): array
    {
        return $this->entityManager->getRepository(CardType::fromCard($card)->requirementClass())
            ->createQueryBuilder('r')
            ->addSelect('rc', 'alt')
            ->leftJoin('r.requiredCard', 'rc')
            ->leftJoin('r.alternativeCards', 'alt')
            ->where('r.mythicCard = :card')
            ->setParameter('card', $card)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Quantités possédées par le joueur pour toutes les cartes citées dans la recette
     * (requises et alternatives), en une seule requête.
     *
     * @param array<int, CardAnimeRequirement|CardFilmRequirement|CardJeuRequirement> $recipe
     *
     * @return array<int, int> quantité indexée par id de carte
     */
    private function ownedQuantities(User $user, CardType $type, array $recipe): array
    {
        $cardIds = [];
        foreach ($recipe as $requirement) {
            if ($requirement->getRequiredCard() !== null) {
                $cardIds[] = $requirement->getRequiredCard()->getId();
            }
            foreach ($requirement->getAlternativeCards() as $altCard) {
                $cardIds[] = $altCard->getId();
            }
        }

        if ($cardIds === []) {
            return [];
        }

        $property = $type->cardProperty();
        $rows = $this->entityManager->getRepository($type->userCardClass())
            ->createQueryBuilder('uc')
            ->select("IDENTITY(uc.$property) AS cardId, uc.quantity AS quantity")
            ->where('uc.user = :user')
            ->andWhere("uc.$property IN (:cardIds)")
            ->setParameter('user', $user)
            ->setParameter('cardIds', array_unique($cardIds))
            ->getQuery()
            ->getArrayResult();

        $quantities = [];
        foreach ($rows as $row) {
            $quantities[(int) $row['cardId']] = (int) $row['quantity'];
        }

        return $quantities;
    }
}
