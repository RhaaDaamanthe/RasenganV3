<?php

namespace App\Controller;

use App\Entity\CardFilm;
use App\Entity\Rarities;
use App\Form\CardFilmType;
use App\Repository\CardFilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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

    /**
     * Range l'image envoyée dans `images/Cartes/Films_<Rareté>/` sous son nom d'origine
     * nettoyé, et renvoie le chemin public à stocker dans `imagePath`.
     *
     * @throws FileException si le dossier ne peut pas être créé ou le fichier déplacé
     */
    private function enregistrerImage(UploadedFile $imageFile, Rarities $rarity): string
    {
        $dossier = 'Films_' . $rarity->getLibelle();
        $targetDirectory = $this->getParameter('app.public_dir') . '/images/Cartes/' . $dossier;

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new FileException(sprintf('Le dossier "%s" n’a pas pu être créé.', $targetDirectory));
        }

        $newFilename = $this->cleanFilename($imageFile->getClientOriginalName());
        $imageFile->move($targetDirectory, $newFilename);

        return 'images/Cartes/' . $dossier . '/' . $newFilename;
    }
    
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
        $form = $this->createForm(CardFilmType::class, $cardFilm, ['image_obligatoire' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Quantité déterminée automatiquement par la rareté choisie
            $cardFilm->setQuantity($cardFilm->getRarity()?->getQuantiteParDefaut() ?? 1);

            try {
                $cardFilm->setImagePath($this->enregistrerImage($form->get('imagePath')->getData(), $cardFilm->getRarity()));
            } catch (FileException $e) {
                $this->addFlash('error', "Erreur lors du téléchargement de l'image. La carte n'a pas été créée : " . $e->getMessage());

                return $this->render('card_film/new.html.twig', [
                    'card_film' => $cardFilm,
                    'form' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $entityManager->persist($cardFilm);
            $entityManager->flush();

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
        $form = $this->createForm(CardFilmType::class, $cardFilm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Quantité déterminée automatiquement par la rareté choisie
            $cardFilm->setQuantity($cardFilm->getRarity()?->getQuantiteParDefaut() ?? 1);

            // Nouvelle image envoyée : elle remplace l'ancienne (sinon on garde l'actuelle).
            $imageFile = $form->get('imagePath')->getData();
            if ($imageFile instanceof UploadedFile) {
                try {
                    $cardFilm->setImagePath($this->enregistrerImage($imageFile, $cardFilm->getRarity()));
                } catch (FileException $e) {
                    $this->addFlash('error', "Erreur lors du téléchargement de l'image. La carte n'a pas été modifiée : " . $e->getMessage());

                    return $this->render('card_film/edit.html.twig', [
                        'card_film' => $cardFilm,
                        'form' => $form,
                    ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
                }
            }

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
                $absolutePath = $this->getParameter('app.public_dir') . '/' . $imagePath;
                if (file_exists($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            $entityManager->remove($cardFilm);
            $entityManager->flush();
            $this->addFlash('success', 'La carte a été supprimée avec succès !');

        }

        return $this->redirectToRoute('app_card_film_index', [], Response::HTTP_SEE_OTHER);
    }
}