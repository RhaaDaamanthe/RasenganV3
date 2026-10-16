<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardFilm;
use App\Entity\CardJeu;
use App\Entity\ShowcaseCard;
use App\Entity\User;
use App\Entity\UserCardAnime;
use App\Entity\UserCardFilm;
use App\Entity\UserCardJeu;
use App\Form\ProfileSettingsType;
use App\Repository\TradeOfferRepository;
use App\Repository\UserCardAnimeRepository;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-compte')]
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_user_profile', methods: ['GET'])]
    public function showProfile(BadgeService $badgeService, TradeOfferRepository $tradeOfferRepository): Response
    {
        $user = $this->getUser();

        return $this->render('user/index.html.twig', [
            'user' => $user,
            'badgeShowcase' => $badgeService->getBadgeShowcase($user),
            'pendingTradesCount' => $tradeOfferRepository->countPendingReceivedBy($user),
        ]);
    }

    #[Route('/parametres', name: 'app_profile_settings', methods: ['GET', 'POST'])]
    public function settings(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserCardAnimeRepository $userCardAnimeRepository
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ProfileSettingsType::class, $user);
        $form->handleRequest($request);

        // RÉCUPÉRER LES CARTES DE L'UTILISATEUR
        $userCardAnimes = $userCardAnimeRepository->findByUserSorted($user);
        $userCardFilms = $entityManager->getRepository(UserCardFilm::class)->findBy(['user' => $user]);
        $userCardJeus = $entityManager->getRepository(UserCardJeu::class)->findBy(['user' => $user]);

        $userCards = [];
        $showcaseChoices = [];
        foreach ($userCardAnimes as $uca) {
            $card = $uca->getCardAnime();
            $userCards[] = $card;
            if ($card && $uca->getQuantity() > 0) {
                $showcaseChoices[] = $this->makeShowcaseChoice($card, 'anime', $card->getAnime()?->getNom());
            }
        }
        foreach ($userCardFilms as $ucf) {
            $card = $ucf->getCardFilm();
            $userCards[] = $card;
            if ($card && $ucf->getQuantity() > 0) {
                $showcaseChoices[] = $this->makeShowcaseChoice($card, 'film', $card->getFilm()?->getNom());
            }
        }
        foreach ($userCardJeus as $ucj) {
            $card = $ucj->getCardJeu();
            $userCards[] = $card;
            if ($card && $ucj->getQuantity() > 0) {
                $showcaseChoices[] = $this->makeShowcaseChoice($card, 'jeu', $card->getJeu()?->getNom());
            }
        }

        usort($showcaseChoices, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $showcaseSelection = [];
        foreach ($entityManager->getRepository(ShowcaseCard::class)->findBy(['user' => $user], ['position' => 'ASC']) as $slot) {
            if ($slot->getCard()) {
                $showcaseSelection[] = $slot->getType().':'.$slot->getCard()->getId();
            }
        }

        // TRI PAR RARETÉ (Commune → Légendaire, puis Mythique et Event) : les ids de
        // rareté suivent cet ordre (voir les constantes de Rarities). Le tri se faisait
        // auparavant sur des libellés ("Commun", "Légendaire") qui ne correspondaient pas
        // à ceux de la base ("Communes", "Legendaires"), si bien qu'il ne triait rien.
        usort($userCards, fn ($a, $b) => ($a->getRarity()?->getId() ?? 99) <=> ($b->getRarity()?->getId() ?? 99));

        // GROUPER PAR RARETÉ
        $cardsByRarity = [];
        foreach ($userCards as $card) {
            $rarity = $card->getRarity()?->getLibelle() ?? 'Inconnue';
            $cardsByRarity[$rarity][] = $card;
        }

        // TRAITEMENT DU FORMULAIRE (pseudo, titre, mdp, image)
        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('user/settings.html.twig', [
            'form' => $form->createView(),
            'cardsByRarity' => $cardsByRarity,     // CARTES TRIÉES PAR RARETÉ
            'currentImage' => $user->getImageCollection(),
            'showcaseChoices' => $showcaseChoices,
            'showcaseSelection' => $showcaseSelection,
            'showcaseCapacity' => ShowcaseCard::DEFAULT_CAPACITY,
        ]);
    }

    #[Route('/parametres/vitrine', name: 'app_profile_showcase_update', methods: ['POST'])]
    public function updateShowcase(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('showcase-update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_profile_settings', ['_fragment' => 'showcase-settings'], Response::HTTP_SEE_OTHER);
        }

        $values = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $request->request->all('showcase_cards'),
        ))));

        if (count($values) > ShowcaseCard::DEFAULT_CAPACITY) {
            $this->addFlash('error', 'Vous pouvez sélectionner au maximum '.ShowcaseCard::DEFAULT_CAPACITY.' cartes.');

            return $this->redirectToRoute('app_profile_settings', ['_fragment' => 'showcase-settings'], Response::HTTP_SEE_OTHER);
        }

        $cards = [];
        foreach ($values as $value) {
            [$type, $id] = array_pad(explode(':', $value, 2), 2, null);
            $card = $this->findOwnedShowcaseCard($user, $type, (int) $id, $entityManager);
            if (!$card) {
                $this->addFlash('error', 'Une carte sélectionnée ne fait pas partie de votre collection.');

                return $this->redirectToRoute('app_profile_settings', ['_fragment' => 'showcase-settings'], Response::HTTP_SEE_OTHER);
            }
            $cards[] = $card;
        }

        foreach ($entityManager->getRepository(ShowcaseCard::class)->findBy(['user' => $user]) as $slot) {
            $entityManager->remove($slot);
        }
        $entityManager->flush();

        foreach ($cards as $index => $card) {
            $slot = (new ShowcaseCard())
                ->setUser($user)
                ->setPosition($index + 1)
                ->setCard($card);
            $entityManager->persist($slot);
        }
        $entityManager->flush();

        $this->addFlash('success', 'Votre vitrine a bien été mise à jour.');

        return $this->redirectToRoute('app_profile_settings', ['_fragment' => 'showcase-settings'], Response::HTTP_SEE_OTHER);
    }

    /**
     * @return array{value: string, name: string, title: string, type: string, imagePath: ?string}
     */
    private function makeShowcaseChoice(CardAnime|CardFilm|CardJeu $card, string $type, ?string $title): array
    {
        return [
            'value' => $type.':'.$card->getId(),
            'name' => $card->getNom(),
            'title' => $title ?? 'Sans œuvre',
            'type' => $type,
            'imagePath' => $card->getImagePath(),
        ];
    }

    private function findOwnedShowcaseCard(User $user, ?string $type, int $id, EntityManagerInterface $entityManager): CardAnime|CardFilm|CardJeu|null
    {
        $configuration = match ($type) {
            'anime' => [UserCardAnime::class, 'cardAnime'],
            'film' => [UserCardFilm::class, 'cardFilm'],
            'jeu' => [UserCardJeu::class, 'cardJeu'],
            default => null,
        };

        if (!$configuration || $id <= 0) {
            return null;
        }

        [$userCardClass, $property] = $configuration;
        $ownership = $entityManager->getRepository($userCardClass)->findOneBy(['user' => $user, $property => $id]);
        if (!$ownership || $ownership->getQuantity() <= 0) {
            return null;
        }

        return match ($type) {
            'anime' => $ownership->getCardAnime(),
            'film' => $ownership->getCardFilm(),
            'jeu' => $ownership->getCardJeu(),
        };
    }
}
