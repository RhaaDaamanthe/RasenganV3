<?php

namespace App\Controller;

use App\Entity\Badge;
use App\Form\BadgeType;
use App\Repository\BadgeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/badges')]
#[IsGranted('ROLE_ADMIN')]
final class BadgeController extends AbstractController
{
    /**
     * Nettoie une chaîne de caractères pour être utilisée comme nom de fichier
     * (enlève les accents, ne garde que alphanumérique/underscore/tiret).
     */
    private function cleanFilename(string $filename): string
    {
        $info = pathinfo($filename);
        $nameWithoutExtension = $info['filename'];
        $extension = $info['extension'] ?? '';

        $cleanedName = iconv('UTF-8', 'ASCII//TRANSLIT', $nameWithoutExtension);
        if ($cleanedName === false) {
            $cleanedName = $nameWithoutExtension;
        }

        $cleanedName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $cleanedName);

        return $cleanedName . (empty($extension) ? '' : '.' . $extension);
    }

    #[Route(name: 'app_badge_index', methods: ['GET'])]
    public function index(BadgeRepository $badgeRepository): Response
    {
        $badges = $badgeRepository->findBy([], ['position' => 'ASC']);

        $groups = ['collectionneur' => [], 'anciennete' => [], 'event' => []];
        foreach ($badges as $badge) {
            $groups[$badge->getType()][] = $badge;
        }

        return $this->render('badge/index.html.twig', [
            'collectorBadges' => $groups['collectionneur'],
            'seniorityBadges' => $groups['anciennete'],
            'eventBadges' => $groups['event'],
        ]);
    }

    #[Route('/new', name: 'app_badge_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, BadgeRepository $badgeRepository): Response
    {
        $badge = new Badge();
        $badge->setType('event');
        $badge->setRarity('legendaire');

        $maxPosition = $badgeRepository->createQueryBuilder('b')
            ->select('MAX(b.position)')
            ->getQuery()
            ->getSingleScalarResult();
        $badge->setPosition(((int) $maxPosition) + 10);

        $form = $this->createForm(BadgeType::class, $badge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $iconFile */
            $iconFile = $form->get('icon')->getData();

            if (!$iconFile) {
                $this->addFlash('error', 'Une icône est obligatoire pour créer un badge.');

                return $this->render('badge/new.html.twig', ['form' => $form]);
            }

            $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/images/badges';
            $newFilename = $this->cleanFilename($iconFile->getClientOriginalName());

            try {
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0777, true);
                }
                $iconFile->move($targetDirectory, $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload de l\'icône : ' . $e->getMessage());

                return $this->render('badge/new.html.twig', ['form' => $form]);
            }

            $badge->setIcon($newFilename);
            $entityManager->persist($badge);
            $entityManager->flush();

            $this->addFlash('success', 'Badge « ' . $badge->getName() . ' » créé avec succès !');

            return $this->redirectToRoute('app_badge_index');
        }

        return $this->render('badge/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'app_badge_edit', methods: ['GET', 'POST'])]
    public function edit(Badge $badge, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BadgeType::class, $badge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $iconFile */
            $iconFile = $form->get('icon')->getData();

            if ($iconFile) {
                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/images/badges';
                $newFilename = $this->cleanFilename($iconFile->getClientOriginalName());

                try {
                    if (!is_dir($targetDirectory)) {
                        mkdir($targetDirectory, 0777, true);
                    }
                    $iconFile->move($targetDirectory, $newFilename);
                    $badge->setIcon($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'icône : ' . $e->getMessage());

                    return $this->render('badge/edit.html.twig', ['form' => $form, 'badge' => $badge]);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Badge « ' . $badge->getName() . ' » modifié avec succès !');

            return $this->redirectToRoute('app_badge_index');
        }

        return $this->render('badge/edit.html.twig', ['form' => $form, 'badge' => $badge]);
    }

    #[Route('/{id}', name: 'app_badge_delete', methods: ['POST'])]
    public function delete(Badge $badge, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $badge->getId(), (string) $request->request->get('_token'))) {
            foreach ($badge->getUsers() as $user) {
                $user->removeBadge($badge);
            }

            $entityManager->remove($badge);
            $entityManager->flush();
            $this->addFlash('success', 'Badge supprimé.');
        }

        return $this->redirectToRoute('app_badge_index');
    }

    #[Route('/{id}/assign', name: 'app_badge_assign', methods: ['GET', 'POST'])]
    public function assign(Badge $badge, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('assign-badge' . $badge->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');

                return $this->redirectToRoute('app_badge_assign', ['id' => $badge->getId()]);
            }

            $checkedIds = array_map('intval', $request->request->all('user_ids'));

            foreach ($userRepository->findAll() as $user) {
                $hasBadge = $user->getBadges()->contains($badge);
                $shouldHave = in_array($user->getId(), $checkedIds, true);

                if ($shouldHave && !$hasBadge) {
                    $user->addBadge($badge);
                } elseif (!$shouldHave && $hasBadge) {
                    $user->removeBadge($badge);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Attribution du badge « ' . $badge->getName() . ' » mise à jour.');

            return $this->redirectToRoute('app_badge_assign', ['id' => $badge->getId()]);
        }

        $users = $userRepository->createQueryBuilder('u')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('badge/assign.html.twig', [
            'badge' => $badge,
            'users' => $users,
        ]);
    }
}
