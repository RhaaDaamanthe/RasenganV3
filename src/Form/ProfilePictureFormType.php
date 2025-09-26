<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProfilePictureFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('profilePicture', FileType::class, [
                'label' => 'Image de profil (Fichier Image)',
                // "mapped" à false indique que ce champ n'est pas directement lié à une propriété de l'entité.
                // C'est le contrôleur qui gérera l'upload du fichier.
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        // Taille maximale du fichier (1 MB)
                        'maxSize' => '15M',
                        // Types de fichiers autorisés
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier image valide (JPG, PNG, GIF).',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Pas de DataClass, car on ne lie pas ce formulaire directement à une entité.
            // On le gère manuellement dans le contrôleur.
        ]);
    }
}
