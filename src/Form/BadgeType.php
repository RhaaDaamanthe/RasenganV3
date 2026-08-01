<?php

namespace App\Form;

use App\Entity\Badge;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class BadgeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du badge',
                'attr' => [
                    'placeholder' => 'Ex : Halloween 2025',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Affichée dans l\'info-bulle du badge...',
                    'rows' => 2,
                    'class' => 'form-control',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Événement (attribué manuellement)' => 'event',
                    'Collectionneur (calculé selon le nombre de cartes)' => 'collectionneur',
                    'Ancienneté (calculé selon la date d\'inscription)' => 'anciennete',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('rarity', ChoiceType::class, [
                'label' => 'Rareté (couleur d\'affichage)',
                'choices' => [
                    'Commune' => 'commune',
                    'Rare' => 'rare',
                    'Épique' => 'epique',
                    'Légendaire' => 'legendaire',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('level', IntegerType::class, [
                'label' => 'Niveau du palier',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('objective', IntegerType::class, [
                'label' => 'Objectif du palier',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('icon', FileType::class, [
                'label' => 'Icône (fichier image)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control-file',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier image valide (JPG, PNG ou WEBP)',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Badge::class,
        ]);
    }
}
