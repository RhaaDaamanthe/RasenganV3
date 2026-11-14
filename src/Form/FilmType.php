<?php

namespace App\Form;

use App\Entity\Film;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du film',
                'attr' => [
                    'placeholder' => 'Entrez le nom du film',
                    'class' => 'form-control'
                ]
            ])
            ->add('surnoms', TextType::class, [
                'label' => 'Surnoms (optionnel)',
                'required' => false,
                'help' => "Autres noms pour retrouver le film dans les recherches, séparés par des virgules.",
                'attr' => [
                    'placeholder' => 'Ex. : LOTR, Lord of the Rings',
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Film::class,
        ]);
    }
}