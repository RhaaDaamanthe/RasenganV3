<?php

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';

// Charge les variables d'environnement
if (file_exists(__DIR__.'/.env.local')) {
    (new Dotenv())->loadEnv(__DIR__.'/.env.local');
} else {
    (new Dotenv())->loadEnv(__DIR__.'/.env');
}

// Configuration du conteneur de services
$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/config'));
$loader->load('services.yaml');
$container->compile();

// Récupère les services
$entityManager = $container->get(EntityManagerInterface::class);
$passwordHasher = $container->get(UserPasswordHasherInterface::class);

// Vos données d'utilisateurs avec les mots de passe en clair
$usersToUpdate = [
    ['email' => 'nicolletjeremy@gmail.com', 'plainPassword' => 'Rhaadaamanthe'],
    ['email' => 'loulipoups57@gmail.com', 'plainPassword' => 'Bao'],
    ['email' => 'Alexas@example.com', 'plainPassword' => 'Alexas'],
    ['email' => 'Astro@example.com', 'plainPassword' => 'Astro'],
    ['email' => 'Citron@example.com', 'plainPassword' => 'Citron'],
    ['email' => 'Emeline@example.com', 'plainPassword' => 'Emeline'],
    ['email' => 'Kenza@example.com', 'plainPassword' => 'Kenza'],
    ['email' => 'Kevin@example.com', 'plainPassword' => 'Kevin'],
    ['email' => 'Loris@example.com', 'plainPassword' => 'Loris'],
    ['email' => 'Micka@example.com', 'plainPassword' => 'Micka'],
    ['email' => 'Roxas@example.com', 'plainPassword' => 'Roxas'],
    ['email' => 'Swaye@example.com', 'plainPassword' => 'Swaye'],
    ['email' => 'Tamo@example.com', 'plainPassword' => 'Tamo'],
    ['email' => 'Xene@example.com', 'plainPassword' => 'Xene'],
    ['email' => 'Niwa@example.com', 'plainPassword' => 'Niwa'],
];

foreach ($usersToUpdate as $userData) {
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $userData['email']]);

    if (!$user) {
        echo "L'utilisateur avec l'email {$userData['email']} n'a pas été trouvé. Saut.\n";
        continue;
    }

    $hashedPassword = $passwordHasher->hashPassword($user, $userData['plainPassword']);
    $user->setPassword($hashedPassword);

    echo "Le mot de passe de l'utilisateur {$user->getPseudo()} a été haché.\n";
}

$entityManager->flush();

echo "Tous les mots de passe ont été mis à jour avec succès.\n";