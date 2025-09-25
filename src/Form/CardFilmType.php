<?php

namespace App\Form;

use App\Entity\CardFilm;
use App\Entity\film;
use App\Entity\Rarities;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CardFilmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('imagePath')
            ->add('description')
            ->add('quantity')
            ->add('rarity', EntityType::class, [
                'class' => Rarities::class,
                'choice_label' => 'id',
            ])
            ->add('film', EntityType::class, [
                'class' => film::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CardFilm::class,
        ]);
    }
}
