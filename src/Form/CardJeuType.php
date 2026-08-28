<?php

namespace App\Form;

use App\Entity\CardJeu;
use App\Entity\Jeu;
use App\Entity\Rarities;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Doctrine\ORM\EntityRepository;

class CardJeuType extends AbstractType
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
            ->add('jeu', EntityType::class, [
                'class' => Jeu::class,
                'choice_label' => 'nom',
                'label' => 'Jeu vidéo',
                'placeholder' => 'Choisissez un jeu vidéo',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('j')
                        ->orderBy('j.nom', 'ASC'); // Tri (A-Z)
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CardJeu::class,
        ]);
    }
}
