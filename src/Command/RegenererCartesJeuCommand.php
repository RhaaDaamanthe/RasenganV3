<?php

namespace App\Command;

use App\Repository\CardJeuRepository;
use App\Service\CardJeuComposer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Régénère l'image de toutes les cartes jeu vidéo créées avec l'éditeur, par exemple après
 * avoir remplacé un cadre (public/images/cadres/jeu/) ou modifié la mise en page dans
 * CardJeuComposer.
 */
#[AsCommand(name: 'app:cartes-jeu:regenerer', description: 'Régénère les images des cartes jeu vidéo créées avec l\'éditeur')]
final class RegenererCartesJeuCommand extends Command
{
    public function __construct(
        private readonly CardJeuRepository $cartes,
        private readonly CardJeuComposer $composer,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cartes = $this->cartes->findComposees();
        if ($cartes === []) {
            $io->success('Aucune carte créée avec l\'éditeur.');

            return Command::SUCCESS;
        }

        $echecs = 0;
        $io->progressStart(count($cartes));
        foreach ($cartes as $carte) {
            try {
                $this->composer->generer($carte);
            } catch (\Throwable $e) {
                ++$echecs;
                $io->newLine();
                $io->error(sprintf('#%d %s : %s', $carte->getId(), $carte->getNom(), $e->getMessage()));
            }
            $io->progressAdvance();
        }
        $io->progressFinish();
        $this->em->flush();

        if ($echecs > 0) {
            $io->warning(sprintf('%d carte(s) régénérée(s), %d échec(s).', count($cartes) - $echecs, $echecs));

            return Command::FAILURE;
        }
        $io->success(sprintf('%d carte(s) régénérée(s).', count($cartes)));

        return Command::SUCCESS;
    }
}
