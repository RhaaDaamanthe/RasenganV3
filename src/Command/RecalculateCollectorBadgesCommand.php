<?php

namespace App\Command;

use App\Entity\User;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:badges:recalculate-collector',
    description: 'Recalcule le badge collectionneur de chaque utilisateur en fonction de son nombre de cartes.',
)]
class RecalculateCollectorBadgesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BadgeService $badgeService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        foreach ($users as $user) {
            $this->badgeService->refreshCollectorBadges($user);
            $output->writeln("{$user->getPseudo()} : {$user->getTotalCardsCount()} cartes.");
        }

        $output->writeln(sprintf('Badges collectionneur recalculés pour %d utilisateur(s).', count($users)));

        return Command::SUCCESS;
    }
}
