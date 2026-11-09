<?php

namespace App\Form;

use App\Entity\CardJeu;
use App\Entity\Jeu;
use App\Entity\Rarities;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * Formulaire de l'éditeur de cartes jeu vidéo : les champs de la carte, l'image du perso
 * et le cadrage (champs cachés remplis par `public/js/card_jeu_editor.js`).
 */
class CardJeuEditeurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $raretes = $options['raretes_disponibles'];

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du perso',
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
                'attr' => ['placeholder' => 'Ex : Sophie', 'maxlength' => 150, 'data-editeur' => 'nom'],
            ])
            ->add('jeu', EntityType::class, [
                'class' => Jeu::class,
                'choice_label' => 'nom',
                'label' => 'Jeu vidéo',
                'placeholder' => 'Choisissez un jeu vidéo',
                'constraints' => [new NotNull(message: 'Le jeu est obligatoire : son nom est écrit sur la carte.')],
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('j')->orderBy('j.nom', 'ASC'),
                'attr' => ['data-editeur' => 'jeu'],
            ])
            ->add('rarity', EntityType::class, [
                'class' => Rarities::class,
                'choice_label' => 'libelle',
                'label' => 'Rareté',
                'placeholder' => 'Choisissez une rareté',
                'constraints' => [new NotNull(message: 'La rareté est obligatoire.')],
                // Seules les raretés dont le cadre existe sont proposées.
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('r')
                    ->where('r.id IN (:ids)')
                    ->setParameter('ids', $raretes ?: [0])
                    ->orderBy('r.id', 'ASC'),
                'attr' => ['data-editeur' => 'rarete'],
            ])
            ->add('numero', IntegerType::class, [
                'label' => 'Numéro',
                'required' => false,
                'constraints' => [new Positive(message: 'Le numéro doit être positif.')],
                'help' => 'Pré-rempli avec le prochain numéro libre (numérotation commune à tous les jeux). Laisser vide pour ne rien afficher.',
                'attr' => ['min' => 1, 'data-editeur' => 'numero'],
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du perso',
                'mapped' => false,
                'required' => $options['image_requise'],
                'help' => $options['image_requise']
                    ? 'JPG, PNG ou WEBP. Idéalement au moins 1350 px de large pour une carte nette.'
                    : 'Laisser vide pour garder l\'image actuelle.',
                'constraints' => array_filter([
                    $options['image_requise'] ? new NotNull(message: 'L\'image du perso est obligatoire.') : null,
                    new File(
                        maxSize: '20M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Veuillez envoyer une image JPG, PNG ou WEBP.',
                    ),
                ]),
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp', 'data-editeur' => 'image'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Décrivez la carte...'],
            ])
            ->add('cadrageX', HiddenType::class, ['mapped' => false, 'attr' => ['data-editeur' => 'cadrage-x']])
            ->add('cadrageY', HiddenType::class, ['mapped' => false, 'attr' => ['data-editeur' => 'cadrage-y']])
            ->add('cadrageScale', HiddenType::class, ['mapped' => false, 'attr' => ['data-editeur' => 'cadrage-scale']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CardJeu::class,
            'image_requise' => true,
            'raretes_disponibles' => [],
        ]);
        $resolver->setAllowedTypes('image_requise', 'bool');
        $resolver->setAllowedTypes('raretes_disponibles', 'int[]');
    }
}
