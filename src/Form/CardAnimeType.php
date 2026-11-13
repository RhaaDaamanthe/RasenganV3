<?php

namespace App\Form;

use App\Entity\Anime;
use App\Entity\CardAnime;
use App\Entity\Rarities;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Doctrine\ORM\EntityRepository;

class CardAnimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la carte',
                'constraints' => [new NotBlank(message: 'Le nom de la carte est obligatoire.')],
                'attr' => [
                    'placeholder' => 'Entrez le nom de la carte',
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Décrivez la carte...',
                    'rows' => 2,
                    'class' => 'form-control',
                ]
            ])
            ->add('imagePath', FileType::class, [
                'label' => 'Image de la carte (fichier image)',
                'mapped' => false,
                // Obligatoire à la création ; à l'édition, laisser vide garde l'image actuelle.
                'required' => $options['image_obligatoire'],
                'attr' => [
                    'class' => 'form-control-file',
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
                'constraints' => [
                    ...($options['image_obligatoire'] ? [new NotNull(message: 'L\'image de la carte est obligatoire.')] : []),
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier image valide (JPG, PNG ou WEBP)',
                    ])
                ],
            ])
            ->add('rarity', EntityType::class, [
                'class' => Rarities::class,
                'choice_label' => 'libelle',
                'label' => 'Rareté',
                'placeholder' => 'Choisissez une rareté',
                'constraints' => [new NotNull(message: 'La rareté est obligatoire.')],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('anime', EntityType::class, [
                'class' => Anime::class,
                'choice_label' => 'nom',
                'label' => 'Animé',
                'placeholder' => 'Choisissez un animé',
                'constraints' => [new NotNull(message: 'L\'animé est obligatoire.')],
                'attr' => [
                    'class' => 'form-control',
                    // Remplacé par un champ de recherche (public/js/select_recherche.js).
                    'data-recherche' => 'Rechercher un animé...',
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('a')
                        ->orderBy('a.nom', 'ASC'); // Tri par nom
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CardAnime::class,
            'image_obligatoire' => false,
        ]);
        $resolver->setAllowedTypes('image_obligatoire', 'bool');
    }
}
