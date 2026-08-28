<?php

namespace App\Controller;

use App\Entity\Jeu;
use App\Form\JeuType;
use App\Repository\JeuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/jeu')]
#[IsGranted('ROLE_ADMIN')]
final class JeuController extends AbstractController
{
    #[Route(name: 'app_jeu_index', methods: ['GET'])]
    public function index(JeuRepository $jeuRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $search = trim($request->query->get('search', ''));

        $qb = $jeuRepository->createQueryBuilder('j')
            ->orderBy('j.nom', 'ASC');

        if ($search !== '') {
            $qb->andWhere('j.nom LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $jeux = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            30
        );

        return $this->render('jeu/index.html.twig', [
            'jeux' => $jeux,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_jeu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $jeu = new Jeu();
        $form = $this->createForm(JeuType::class, $jeu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($jeu);
            $entityManager->flush();

            $this->addFlash('success', 'Le jeu vidéo a été créé avec succès !');
            return $this->redirectToRoute('app_jeu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('jeu/new.html.twig', [
            'jeu' => $jeu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_jeu_show', methods: ['GET'])]
    public function show(Jeu $jeu): Response
    {
        return $this->render('jeu/show.html.twig', [
            'jeu' => $jeu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_jeu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Jeu $jeu, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(JeuType::class, $jeu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le jeu vidéo a été modifié avec succès !');
            return $this->redirectToRoute('app_jeu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('jeu/edit.html.twig', [
            'jeu' => $jeu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_jeu_delete', methods: ['POST'])]
    public function delete(Request $request, Jeu $jeu, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$jeu->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($jeu);
            $entityManager->flush();
            $this->addFlash('success', 'Le jeu vidéo a été supprimé avec succès !');
        }

        return $this->redirectToRoute('app_jeu_index', [], Response::HTTP_SEE_OTHER);
    }
}
