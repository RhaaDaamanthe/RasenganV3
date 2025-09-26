<?php

namespace App\Controller;

use App\Entity\CardAnime;
use App\Form\CardAnimeType;
use App\Repository\CardAnimeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException; // Import ajouté
use Symfony\Component\String\Slugger\SluggerInterface; // Import ajouté
use Symfony\Component\HttpFoundation\File\UploadedFile; // Import ajouté

#[Route('/card/anime')]
#[IsGranted('ROLE_ADMIN')]
final class CardAnimeController extends AbstractController
{
    #[Route(name: 'app_card_anime_index', methods: ['GET'])]
    public function index(CardAnimeRepository $cardAnimeRepository): Response
    {
        return $this->render('card_anime/index.html.twig', [
            'card_animes' => $cardAnimeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_card_anime_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response // SluggerInterface a été ajouté ici
    {
        $cardAnime = new CardAnime();
        $form = $this->createForm(CardAnimeType::class, $cardAnime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imagePath')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the filename as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image.');
                }
                $cardAnime->setImagePath($newFilename);
            }

            $entityManager->persist($cardAnime);
            $entityManager->flush();

            // Ajoute un message flash de succès
            $this->addFlash('success', 'La carte a été créée avec succès !');

            // Redirige vers la page de création, pour afficher le formulaire vide et le message de succès
            return $this->redirectToRoute('app_card_anime_new', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('card_anime/new.html.twig', [
            'card_anime' => $cardAnime,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_card_anime_show', methods: ['GET'])]
    public function show(CardAnime $cardAnime): Response
    {
        return $this->render('card_anime/show.html.twig', [
            'card_anime' => $cardAnime,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_card_anime_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CardAnime $cardAnime, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CardAnimeType::class, $cardAnime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_card_anime_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('card_anime/edit.html.twig', [
            'card_anime' => $cardAnime,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_card_anime_delete', methods: ['POST'])]
    public function delete(Request $request, CardAnime $cardAnime, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cardAnime->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($cardAnime);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_card_anime_index', [], Response::HTTP_SEE_OTHER);
    }
}
