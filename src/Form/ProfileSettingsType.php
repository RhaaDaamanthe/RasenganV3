<?php

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProfileSettingsType extends AbstractType
{
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

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
                new Callback([$this, 'validatePseudoUniqueness']),
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

    public function validatePseudoUniqueness($pseudo, ExecutionContextInterface $context): void
    {
        $user = $context->getRoot()->getData(); // L'utilisateur connecté
        if (!$user || !$user->getId()) {
            return;
        }

        $existingUser = $this->userRepository->findOneBy(['pseudo' => $pseudo]);

        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $context->buildViolation('Ce pseudo est déjà utilisé par un autre compte.')
                ->atPath('pseudo')
                ->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}