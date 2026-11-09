<?php

namespace App\Controller;

use App\Entity\CardJeu;
use App\Form\CardJeuEditeurType;
use App\Repository\CardJeuRepository;
use App\Service\Cadrage;
use App\Service\CardJeuComposer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Éditeur de cartes jeu vidéo : l'admin choisit l'image du perso, la place et la zoome
 * sur un aperçu en direct, puis le serveur génère l'image finale (cadre + perso + textes)
 * avec `CardJeuComposer`.
 */
#[Route('/admin/cartes-jeu')]
#[IsGranted('ROLE_ADMIN')]
final class CardJeuEditeurController extends AbstractController
{
    public function __construct(
        private readonly CardJeuComposer $composer,
        private readonly EntityManagerInterface $em,
        private readonly CardJeuRepository $cartes,
        private readonly Packages $packages,
    ) {
    }

    #[Route('/creer', name: 'app_card_jeu_editeur_new', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request): Response
    {
        if ($this->composer->raretesDisponibles() === []) {
            $this->addFlash('error', 'Aucun cadre de carte n\'est installé (dossier public/' . CardJeuComposer::DOSSIER_CADRES . ').');

            return $this->redirectToRoute('app_card_jeu_index');
        }

        $carte = new CardJeu();
        $form = $this->createForm(CardJeuEditeurType::class, $carte, [
            'raretes_disponibles' => $this->composer->raretesDisponibles(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $fichier */
            $fichier = $form->get('image')->getData();

            try {
                $source = $this->composer->enregistrerSource($fichier);
            } catch (\Throwable $e) {
                $form->get('image')->addError(new FormError($e->getMessage()));

                return $this->afficher('card_jeu_editeur/new.html.twig', $form, $carte);
            }

            $carte->setQuantity($carte->getRarity()?->getQuantiteParDefaut() ?? 1);
            $carte->setComposition($this->composition($form, $source['chemin'], $source['largeur'], $source['hauteur']));

            try {
                // Premier flush pour obtenir l'id (il entre dans le nom du fichier généré).
                $this->em->persist($carte);
                $this->em->flush();
                $this->composer->generer($carte);
                $this->em->flush();
            } catch (\Throwable $e) {
                if ($carte->getId() !== null) {
                    $this->em->remove($carte);
                    $this->em->flush();
                }
                $this->composer->supprimer($source['chemin']);
                $this->composer->supprimer($carte->getImagePath());
                $this->addFlash('error', 'La carte n\'a pas pu être générée : ' . $e->getMessage());

                return $this->afficher('card_jeu_editeur/new.html.twig', $form, $carte);
            }

            $this->addFlash('success', sprintf('La carte « %s » a été créée.', $carte->getNom()));

            return $this->redirectToRoute('app_card_jeu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->afficher('card_jeu_editeur/new.html.twig', $form, $carte);
    }

    /**
     * Recadrage / modification d'une carte. Une carte montée à la main (sans composition)
     * peut passer à l'éditeur, à condition d'envoyer l'image du perso.
     */
    #[Route('/{id}/editer', name: 'app_card_jeu_editeur_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editer(Request $request, CardJeu $carte): Response
    {
        $etaitComposee = $carte->isComposee();
        $ancienneComposition = $carte->getComposition();
        $ancienneImage = $carte->getImagePath();

        $form = $this->createForm(CardJeuEditeurType::class, $carte, [
            'image_requise' => !$etaitComposee,
            'raretes_disponibles' => $this->composer->raretesDisponibles(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $fichier */
            $fichier = $form->get('image')->getData();
            $nouvelleSource = null;

            try {
                if ($fichier !== null) {
                    $nouvelleSource = $this->composer->enregistrerSource($fichier);
                    $source = $nouvelleSource;
                } else {
                    [$largeur, $hauteur] = $this->composer->dimensionsSource($ancienneComposition['source']);
                    $source = ['chemin' => $ancienneComposition['source'], 'largeur' => $largeur, 'hauteur' => $hauteur];
                }

                $carte->setQuantity($carte->getRarity()?->getQuantiteParDefaut() ?? 1);
                $carte->setComposition($this->composition($form, $source['chemin'], $source['largeur'], $source['hauteur']));
                if (!$etaitComposee) {
                    // L'image montée à la main est conservée sur le disque : generer() ne
                    // doit pas la supprimer en la remplaçant.
                    $carte->setImagePath(null);
                }
                $this->composer->generer($carte);
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->composer->supprimer($nouvelleSource['chemin'] ?? null);
                $this->addFlash('error', 'La carte n\'a pas pu être générée : ' . $e->getMessage());
                $this->em->refresh($carte);

                return $this->redirectToRoute('app_card_jeu_editeur_edit', ['id' => $carte->getId()]);
            }

            if ($nouvelleSource !== null && $ancienneComposition !== null) {
                $this->composer->supprimer($ancienneComposition['source']);
            }

            $this->addFlash('success', sprintf('La carte « %s » a été mise à jour.', $carte->getNom()));

            return $this->redirectToRoute('app_card_jeu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->afficher('card_jeu_editeur/edit.html.twig', $form, $carte, $ancienneImage);
    }

    /**
     * Cadrage choisi dans l'éditeur, borné côté serveur ; cadrage par défaut si les champs
     * cachés sont vides (JS désactivé) ou incohérents.
     */
    private function composition(FormInterface $form, string $source, int $largeur, int $hauteur): array
    {
        $cadrage = Cadrage::depuisSaisie(
            $form->get('cadrageX')->getData(),
            $form->get('cadrageY')->getData(),
            $form->get('cadrageScale')->getData(),
        );
        $cadrage = $cadrage !== null && $cadrage->scale > 0
            ? CardJeuComposer::borner($cadrage, $largeur, $hauteur)
            : CardJeuComposer::cadrageParDefaut($largeur, $hauteur);

        return ['source' => $source, 'x' => $cadrage->x, 'y' => $cadrage->y, 'scale' => $cadrage->scale];
    }

    private function afficher(string $template, FormInterface $form, CardJeu $carte, ?string $imageActuelle = null): Response
    {
        $config = $this->composer->configurationEditeur();
        $config['police'] = $this->packages->getUrl($config['police']);
        foreach ($config['raretes'] as $id => $rarete) {
            $config['raretes'][$id]['cadre'] = $this->packages->getUrl($rarete['cadre']);
        }

        $composition = $carte->getComposition();
        $config['initial'] = $composition !== null ? [
            'source' => $this->packages->getUrl($composition['source']),
            'x' => $composition['x'],
            'y' => $composition['y'],
            'scale' => $composition['scale'],
        ] : null;
        // Sur la page de création, le numéro suit le jeu choisi tant que l'admin ne l'a pas modifié.
        $config['prochainsNumeros'] = $carte->getId() === null ? (object) $this->cartes->findProchainsNumerosParJeu() : null;

        return $this->render($template, [
            'form' => $form,
            'carte' => $carte,
            'image_actuelle' => $imageActuelle,
            'config_editeur' => $config,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }
}
