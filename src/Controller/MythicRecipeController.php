<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Entity\CardAnimeRequirement;
use App\Entity\CardFilm;
use App\Entity\CardFilmRequirement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/mythic-recipes')]
#[IsGranted('ROLE_ADMIN')]
final class MythicRecipeController extends AbstractController
{
    #[Route(name: 'app_mythic_recipe_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $mythicAnimeCards = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('c')
            ->join('c.rarity', 'r')
            ->where('r.id = 5')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $mythicFilmCards = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('c')
            ->join('c.rarity', 'r')
            ->where('r.id = 5')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/mythic_recipes/index.html.twig', [
            'mythicAnimeCards' => $mythicAnimeCards,
            'mythicFilmCards' => $mythicFilmCards,
        ]);
    }

    #[Route('/anime/{id}', name: 'app_mythic_recipe_edit_anime', methods: ['GET', 'POST'])]
    public function editAnime(CardAnime $card, Request $request, EntityManagerInterface $entityManager): Response
    {
        $candidates = $entityManager->getRepository(CardAnime::class)
            ->createQueryBuilder('c')
            ->where('c.id != :id')
            ->setParameter('id', $card->getId())
            ->leftJoin('c.anime', 'a')
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mythic-recipe-edit' . $card->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');

                return $this->redirectToRoute('app_mythic_recipe_edit_anime', ['id' => $card->getId()]);
            }

            foreach ($card->getRequirements() as $existingRequirement) {
                $entityManager->remove($existingRequirement);
            }

            $requiredIds = $request->request->all('required');
            $quantities = $request->request->all('qty');
            $placeholders = $request->request->all('placeholder');
            $alternativesRaw = $request->request->all('alternatives');

            foreach ($requiredIds as $index => $requiredId) {
                $requiredId = trim((string) $requiredId);
                $placeholderNom = trim((string) ($placeholders[$index] ?? ''));

                if ($requiredId !== '' && (int) $requiredId !== $card->getId()) {
                    $requiredCard = $entityManager->getRepository(CardAnime::class)->find($requiredId);
                    if ($requiredCard === null) {
                        continue;
                    }

                    $requirement = new CardAnimeRequirement();
                    $requirement->setMythicCard($card);
                    $requirement->setRequiredCard($requiredCard);
                    $requirement->setQuantityRequired(max(1, (int) ($quantities[$index] ?? 1)));

                    $altIds = array_filter(array_map('trim', explode(',', (string) ($alternativesRaw[$index] ?? ''))), fn ($v) => $v !== '');
                    foreach ($altIds as $altId) {
                        $altCard = $entityManager->getRepository(CardAnime::class)->find($altId);
                        if ($altCard !== null && $altCard->getId() !== $requiredCard->getId()) {
                            $requirement->addAlternativeCard($altCard);
                        }
                    }

                    $entityManager->persist($requirement);
                } elseif ($placeholderNom !== '') {
                    $requirement = new CardAnimeRequirement();
                    $requirement->setMythicCard($card);
                    $requirement->setRequiredCard(null);
                    $requirement->setPlaceholderNom($placeholderNom);
                    $requirement->setQuantityRequired(max(1, (int) ($quantities[$index] ?? 1)));
                    $entityManager->persist($requirement);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Combinaison mise à jour avec succès !');

            return $this->redirectToRoute('app_mythic_recipe_index');
        }

        return $this->render('admin/mythic_recipes/edit.html.twig', [
            'card' => $card,
            'type' => 'anime',
            'candidates' => $candidates,
        ]);
    }

    #[Route('/film/{id}', name: 'app_mythic_recipe_edit_film', methods: ['GET', 'POST'])]
    public function editFilm(CardFilm $card, Request $request, EntityManagerInterface $entityManager): Response
    {
        $candidates = $entityManager->getRepository(CardFilm::class)
            ->createQueryBuilder('c')
            ->where('c.id != :id')
            ->setParameter('id', $card->getId())
            ->leftJoin('c.film', 'f')
            ->orderBy('f.nom', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mythic-recipe-edit' . $card->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');

                return $this->redirectToRoute('app_mythic_recipe_edit_film', ['id' => $card->getId()]);
            }

            foreach ($card->getRequirements() as $existingRequirement) {
                $entityManager->remove($existingRequirement);
            }

            $requiredIds = $request->request->all('required');
            $quantities = $request->request->all('qty');
            $placeholders = $request->request->all('placeholder');
            $alternativesRaw = $request->request->all('alternatives');

            foreach ($requiredIds as $index => $requiredId) {
                $requiredId = trim((string) $requiredId);
                $placeholderNom = trim((string) ($placeholders[$index] ?? ''));

                if ($requiredId !== '' && (int) $requiredId !== $card->getId()) {
                    $requiredCard = $entityManager->getRepository(CardFilm::class)->find($requiredId);
                    if ($requiredCard === null) {
                        continue;
                    }

                    $requirement = new CardFilmRequirement();
                    $requirement->setMythicCard($card);
                    $requirement->setRequiredCard($requiredCard);
                    $requirement->setQuantityRequired(max(1, (int) ($quantities[$index] ?? 1)));

                    $altIds = array_filter(array_map('trim', explode(',', (string) ($alternativesRaw[$index] ?? ''))), fn ($v) => $v !== '');
                    foreach ($altIds as $altId) {
                        $altCard = $entityManager->getRepository(CardFilm::class)->find($altId);
                        if ($altCard !== null && $altCard->getId() !== $requiredCard->getId()) {
                            $requirement->addAlternativeCard($altCard);
                        }
                    }

                    $entityManager->persist($requirement);
                } elseif ($placeholderNom !== '') {
                    $requirement = new CardFilmRequirement();
                    $requirement->setMythicCard($card);
                    $requirement->setRequiredCard(null);
                    $requirement->setPlaceholderNom($placeholderNom);
                    $requirement->setQuantityRequired(max(1, (int) ($quantities[$index] ?? 1)));
                    $entityManager->persist($requirement);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Combinaison mise à jour avec succès !');

            return $this->redirectToRoute('app_mythic_recipe_index');
        }

        return $this->render('admin/mythic_recipes/edit.html.twig', [
            'card' => $card,
            'type' => 'film',
            'candidates' => $candidates,
        ]);
    }
}
