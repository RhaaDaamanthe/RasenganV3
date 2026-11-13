<?php

namespace App\Controller;

use App\Entity\CardJeu;
use App\Entity\Rarities;
use App\Form\CardJeuType;
use App\Repository\CardJeuRepository;
use App\Service\CardJeuComposer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/card/jeu')]
#[IsGranted('ROLE_ADMIN')]
final class CardJeuController extends AbstractController
{
    /**
     * Nettoie une chaîne de caractères pour être utilisée comme nom de fichier.
     * Enlève les accents et normalise sans slugifier (conserve les underscores).
     */
    private function cleanFilename(string $filename): string
    {
        // 1. Enlever l'extension
        $info = pathinfo($filename);
        $nameWithoutExtension = $info['filename'];
        $extension = $info['extension'] ?? '';

        // 2. Normalisation et suppression des accents (sans conversion espace/underscore en tiret)
        // Utilisation de la fonction iconv pour supprimer les accents sans utiliser le Slugger
        $cleanedName = iconv('UTF-8', 'ASCII//TRANSLIT', $nameWithoutExtension);
        if ($cleanedName === false) {
             // Fallback si iconv échoue, on renvoie au moins le nom sans extension
             $cleanedName = $nameWithoutExtension;
        }

        // 3. Supprimer les caractères qui ne sont ni alphanumériques, ni underscore, ni tiret (sécurité)
        $cleanedName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $cleanedName);

        // 4. Réassembler
        return $cleanedName . (empty($extension) ? '' : '.' . $extension);
    }

    /**
     * Range l'image envoyée dans `images/Cartes/Jeux_<Rareté>/` sous son nom d'origine
     * nettoyé, et renvoie le chemin public à stocker dans `imagePath`.
     *
     * @throws FileException si le dossier ne peut pas être créé ou le fichier déplacé
     */
    private function enregistrerImage(UploadedFile $imageFile, Rarities $rarity): string
    {
        $dossier = 'Jeux_' . $rarity->getLibelle();
        $targetDirectory = $this->getParameter('app.public_dir') . '/images/Cartes/' . $dossier;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new FileException(sprintf('Le dossier "%s" n’a pas pu être créé.', $targetDirectory));
        }

        $newFilename = $this->cleanFilename($imageFile->getClientOriginalName());
        $imageFile->move($targetDirectory, $newFilename);

        return 'images/Cartes/' . $dossier . '/' . $newFilename;
    }

    #[Route(name: 'app_card_jeu_index', methods: ['GET'])]
    public function index(CardJeuRepository $cardJeuRepository): Response
    {
        return $this->render('card_jeu/index.html.twig', [
            'card_jeus' => $cardJeuRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_card_jeu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $cardJeu = new CardJeu();
        $form = $this->createForm(CardJeuType::class, $cardJeu, ['image_obligatoire' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Quantité déterminée automatiquement par la rareté choisie
            $cardJeu->setQuantity($cardJeu->getRarity()?->getQuantiteParDefaut() ?? 1);

            try {
                $cardJeu->setImagePath($this->enregistrerImage($form->get('imagePath')->getData(), $cardJeu->getRarity()));
            } catch (FileException $e) {
                $this->addFlash('error', "Erreur lors du téléchargement de l'image. La carte n'a pas été créée : " . $e->getMessage());

                return $this->render('card_jeu/new.html.twig', [
                    'card_jeu' => $cardJeu,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $entityManager->persist($cardJeu);
            $entityManager->flush();

            $this->addFlash('success', 'La carte a été créée avec succès !');
            return $this->redirectToRoute('app_card_jeu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('card_jeu/new.html.twig', [
            'card_jeu' => $cardJeu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_card_jeu_show', methods: ['GET'])]
    public function show(CardJeu $cardJeu): Response
    {
        return $this->render('card_jeu/show.html.twig', [
            'card_jeu' => $cardJeu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_card_jeu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CardJeu $cardJeu, EntityManagerInterface $entityManager): Response
    {
        // Une carte générée par l'éditeur se modifie dans l'éditeur, qui régénère son image.
        if ($cardJeu->isComposee()) {
            return $this->redirectToRoute('app_card_jeu_editeur_edit', ['id' => $cardJeu->getId()]);
        }

        $form = $this->createForm(CardJeuType::class, $cardJeu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Quantité déterminée automatiquement par la rareté choisie
            $cardJeu->setQuantity($cardJeu->getRarity()?->getQuantiteParDefaut() ?? 1);

            // Nouvelle image envoyée : elle remplace l'ancienne (sinon on garde l'actuelle).
            $imageFile = $form->get('imagePath')->getData();
            if ($imageFile instanceof UploadedFile) {
                try {
                    $cardJeu->setImagePath($this->enregistrerImage($imageFile, $cardJeu->getRarity()));
                } catch (FileException $e) {
                    $this->addFlash('error', "Erreur lors du téléchargement de l'image. La carte n'a pas été modifiée : " . $e->getMessage());

                    return $this->render('card_jeu/edit.html.twig', [
                        'card_jeu' => $cardJeu,
                        'form' => $form,
                    ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'La carte a été modifiée avec succès !');

            return $this->redirectToRoute('app_card_jeu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('card_jeu/edit.html.twig', [
            'card_jeu' => $cardJeu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_card_jeu_delete', methods: ['POST'])]
    public function delete(Request $request, CardJeu $cardJeu, EntityManagerInterface $entityManager, CardJeuComposer $composer): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cardJeu->getId(), $request->getPayload()->getString('_token'))) {

            // Carte générée par l'éditeur : son image source part avec elle.
            $composer->supprimer($cardJeu->getComposition()['source'] ?? null);

            // Suppression optionnelle du fichier physique
            $imagePath = $cardJeu->getImagePath();
            if ($imagePath) {
                $absolutePath = $this->getParameter('app.public_dir') . '/' . $imagePath;
                if (file_exists($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            $entityManager->remove($cardJeu);
            $entityManager->flush();
            $this->addFlash('success', 'La carte a été supprimée avec succès !');

        }

        return $this->redirectToRoute('app_card_jeu_index', [], Response::HTTP_SEE_OTHER);
    }
}
