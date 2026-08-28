<?php

namespace App\Controller;

use App\Entity\CardJeu;
use App\Form\CardJeuType;
use App\Repository\CardJeuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface; // Gardé pour référence, mais non utilisé pour le nom
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

    #[Route(name: 'app_card_jeu_index', methods: ['GET'])]
    public function index(CardJeuRepository $cardJeuRepository): Response
    {
        return $this->render('card_jeu/index.html.twig', [
            'card_jeus' => $cardJeuRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_card_jeu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $cardJeu = new CardJeu();
        $form = $this->createForm(CardJeuType::class, $cardJeu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imagePath')->getData();

            // Quantité déterminée automatiquement par la rareté choisie
            $cardJeu->setQuantity($cardJeu->getRarity()?->getQuantiteParDefaut() ?? 1);

            // Persister l'entité pour obtenir l'ID (utile même si l'ID n'est pas dans le nom)
            $entityManager->persist($cardJeu);
            $entityManager->flush();

            if ($imageFile) {

                // A. Détermination du dossier cible basé sur la rareté (ex: "Legendaires")
                $rarityObject = $cardJeu->getRarity();
                if ($rarityObject === null) {
                    $this->addFlash('error', 'La rareté de la carte est obligatoire.');
                    $entityManager->remove($cardJeu);
                    $entityManager->flush();
                    return $this->redirectToRoute('app_card_jeu_new', [], Response::HTTP_SEE_OTHER);
                }

                $rarityFolderName = 'Jeux_' . $rarityObject->getLibelle();
                $targetDirectory = $this->getParameter('app.public_dir') . '/images/Cartes/' . $rarityFolderName;

                // B. Utilisation du nom de Fichier ORIGINAL (Nettoyé pour la sécurité)
                $originalFilenameWithExtension = $imageFile->getClientOriginalName();
                $newFilename = $this->cleanFilename($originalFilenameWithExtension);

                // C. Déplacement du fichier
                try {
                    // Création des dossiers s'ils n'existent pas
                    if (!is_dir($targetDirectory)) {
                        if (!mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                            throw new \RuntimeException(sprintf('Directory "%s" was not created', $targetDirectory));
                        }
                    }

                    $imageFile->move($targetDirectory, $newFilename);

                    // D. Chemin Public à stocker en base de données : images/Cartes/{Rareté}/NomOriginal.jpg
                    $publicPath = 'images/Cartes/' . $rarityFolderName . '/' . $newFilename;

                    // Mise à jour de l'entité avec le chemin correct
                    $cardJeu->setImagePath($publicPath);

                    // Étape 2 : Re-flusher pour sauvegarder le chemin de l'image
                    $entityManager->flush();

                } catch (FileException $e) {
                    $entityManager->remove($cardJeu);
                    $entityManager->flush();
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image. La carte n\'a pas été créée : ' . $e->getMessage());
                    return $this->redirectToRoute('app_card_jeu_new', [], Response::HTTP_SEE_OTHER);
                }
            } else {
                 $this->addFlash('warning', 'La carte a été créée sans image. Pensez à l\'éditer pour en ajouter une.');
            }

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
        // La logique d'édition d'image est omise ici.
        $form = $this->createForm(CardJeuType::class, $cardJeu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Quantité déterminée automatiquement par la rareté choisie
            $cardJeu->setQuantity($cardJeu->getRarity()?->getQuantiteParDefaut() ?? 1);

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
    public function delete(Request $request, CardJeu $cardJeu, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cardJeu->getId(), $request->getPayload()->getString('_token'))) {

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
