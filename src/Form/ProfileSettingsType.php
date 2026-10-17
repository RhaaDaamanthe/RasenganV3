<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileSettingsType extends AbstractType
{
public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('pseudo', TextType::class, [
            'label' => 'Pseudo',
            'attr' => ['class' => 'form-control'],
            'constraints' => [
                new NotBlank(['message' => 'Le pseudo est obligatoire.']),
                new Length([
                    'min' => 3,
                    'max' => 25,
                    'minMessage' => 'Minimum 3 caractères',
                    'maxMessage' => 'Maximum 25 caractères',
                ]),
                new Regex([
                    'pattern' => '/^[a-zA-Z0-9À-ÿ\s\-_]+$/u',
                    'message' => 'Caractères autorisés : lettres, chiffres, espaces, tirets, underscores',
                ]),
            ],
        ])
        ->add('titreCollection', TextType::class, [
            'label' => 'Titre de ta collection',
            'required' => false,
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Ex: Chez le Roi des Saiyans',
            ],
        ])
        ->add('discordId', TextType::class, [
            'label' => 'Ton identifiant Discord',
            'required' => false,
            'help' => 'Discord > Parametres > Avance > Mode developpeur, puis clic droit sur ton pseudo > Copier l identifiant. Sert a te mentionner quand tu obtiens une carte.',
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Ex: 123456789012345678',
                'inputmode' => 'numeric',
            ],
            'constraints' => [
                new Regex([
                    'pattern' => '/^[0-9]{17,20}$/',
                    'message' => 'Un identifiant Discord est une suite de 17 a 20 chiffres.',
                ]),
            ],
        ])
        ->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'required' => false,
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'attr' => ['class' => 'form-control'],
            ],
            'second_options' => [
                'label' => 'Confirmer',
                'attr' => ['class' => 'form-control'],
            ],
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
            'constraints' => [
                new Length(['min' => 6, 'minMessage' => 'Minimum 6 caractères']),
            ],
        ])
        ->add('imageCollection', TextType::class, [
            'label' => false,
            'required' => false,
            'mapped' => true,
            'attr' => [
                'id' => 'profile_settings_imageCollection',
                'style' => 'display: none;'
            ]
        ]);
}

    // Unicité du pseudo : contrainte UniqueEntity portée par User.
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}