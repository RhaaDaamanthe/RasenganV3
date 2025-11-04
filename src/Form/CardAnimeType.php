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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Doctrine\ORM\EntityRepository;

class CardAnimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la carte',
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
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'data' => 1,
                'attr' => [
                    'min' => 1,
                    'value' => 1,
                    'class' => 'form-control'
                ]
            ])
            ->add('imagePath', FileType::class, [
                'label' => 'Image de la carte (fichier image)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control-file'
                ],
                'constraints' => [
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
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('anime', EntityType::class, [
                'class' => Anime::class,
                'choice_label' => 'nom',
                'label' => 'Animé',
                'placeholder' => 'Choisissez un animé',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
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
        ]);
    }
}
