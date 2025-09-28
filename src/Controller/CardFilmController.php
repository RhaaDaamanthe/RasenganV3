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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface; // Gardé pour référence, mais non utilisé pour le nom
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/card/film')]
#[IsGranted('ROLE_ADMIN')]
final class CardFilmController extends AbstractController
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
    
    #[Route(name: 'app_card_film_index', methods: ['GET'])]
    public function index(CardFilmRepository $cardFilmRepository): Response
    {
        return $this->render('card_film/index.html.twig', [
            'card_films' => $cardFilmRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_card_film_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response 
    {
        $cardFilm = new CardFilm();
        $form = $this->createForm(CardFilmType::class, $cardFilm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imagePath')->getData();

            // Persister l'entité pour obtenir l'ID (utile même si l'ID n'est pas dans le nom)
            $entityManager->persist($cardFilm);
            $entityManager->flush();
            
            if ($imageFile) {
                
                // A. Détermination du dossier cible basé sur la rareté (ex: "Legendaires")
                $rarityObject = $cardFilm->getRarity();
                if ($rarityObject === null) {
                    $this->addFlash('error', 'La rareté de la carte est obligatoire.');
                    $entityManager->remove($cardFilm);
                    $entityManager->flush();
                    return $this->redirectToRoute('app_card_film_new', [], Response::HTTP_SEE_OTHER);
                }
                
                $rarityFolderName = 'Films_' . $rarityObject->getLibelle();
                $targetDirectory = $this->getParameter('kernel.project_dir') . '/public/images/Cartes/' . $rarityFolderName;


                // B. Utilisation du nom de Fichier ORIGINAL (Nettoyé pour la sécurité)
                
                // On utilise le nom de fichier original (ex: "91_Buccel.jpg")
                $originalFilenameWithExtension = $imageFile->getClientOriginalName();
                
                // On nettoie le nom de fichier pour la sécurité, mais sans modifier l'underscore (_)
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
                    $cardFilm->setImagePath($publicPath);
                    
                    // Étape 2 : Re-flusher pour sauvegarder le chemin de l'image
                    $entityManager->flush();
                    
                } catch (FileException $e) {
                    $entityManager->remove($cardFilm);
                    $entityManager->flush();
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image. La carte n\'a pas été créée : ' . $e->getMessage());
                    return $this->redirectToRoute('app_card_film_new', [], Response::HTTP_SEE_OTHER);
                }
            } else {
                 $this->addFlash('warning', 'La carte a été créée sans image. Pensez à l\'éditer pour en ajouter une.');
            }

            $this->addFlash('success', 'La carte a été créée avec succès !');
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
        // La logique d'édition d'image est omise ici.
        $form = $this->createForm(CardFilmType::class, $cardFilm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'La carte a été modifiée avec succès !');

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
            
            // Suppression optionnelle du fichier physique
            $imagePath = $cardFilm->getImagePath();
            if ($imagePath) {
                $absolutePath = $this->getParameter('kernel.project_dir') . '/public/' . $imagePath;
                if (file_exists($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            $entityManager->remove($cardFilm);
            $entityManager->flush();
            $this->addFlash('success', 'La carte a été supprimée avec succès !');

        }

        return $this->redirectToRoute('app_card_film_new', [], Response::HTTP_SEE_OTHER);
    }
}