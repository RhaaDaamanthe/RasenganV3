<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:hash-passwords',
    description: 'Hashes passwords for existing users.',
)]
class HashPasswordsCommand extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userData['email']]);

            if (!$user) {
                $output->writeln("L'utilisateur avec l'email {$userData['email']} n'a pas été trouvé. Saut.");
                continue;
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['plainPassword']);
            $user->setPassword($hashedPassword);

            $output->writeln("Le mot de passe de l'utilisateur {$user->getPseudo()} a été haché.");
        }

        $this->entityManager->flush();

        $output->writeln("Tous les mots de passe ont été mis à jour avec succès.");

        return Command::SUCCESS;
    }
}