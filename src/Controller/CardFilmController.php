<?php

namespace App\Controller;

use App\Entity\CardFilm;
use App\Form\CardFilmType;
use App\Repository\CardFilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/card/film')]
#[IsGranted('ROLE_ADMIN')] // Protection de toutes les routes de ce contrôleur
final class CardFilmController extends AbstractController
{
    #[Route(name: 'app_card_film_index', methods: ['GET'])]
    public function index(CardFilmRepository $cardFilmRepository): Response
    {
        return $this->render('card_film/index.html.twig', [
            'card_films' => $cardFilmRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_card_film_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $cardFilm = new CardFilm();
        $form = $this->createForm(CardFilmType::class, $cardFilm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($cardFilm);
            $entityManager->flush();

            return $this->redirectToRoute('app_card_film_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('card_film/new.html.twig', [
            'card_film' => $cardFilm,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_card_film_show', methods: ['GET'])]
    public function show(CardFilm $cardFilm): Response
    {
        return $this->render('card_film/show.html.twig', [
            'card_film' => $cardFilm,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_card_film_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CardFilm $cardFilm, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CardFilmType::class, $cardFilm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_card_film_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('card_film/edit.html.twig', [
            'card_film' => $cardFilm,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_card_film_delete', methods: ['POST'])]
    public function delete(Request $request, CardFilm $cardFilm, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cardFilm->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($cardFilm);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_card_film_index', [], Response::HTTP_SEE_OTHER);
    }
}
