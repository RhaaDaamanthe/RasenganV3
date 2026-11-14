<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Surnoms des animés, films et jeux vidéo (recherche par autres noms : SNK, Attack on Titan...)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE anime ADD surnoms TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE film ADD surnoms TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE jeu ADD surnoms TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE jeu DROP surnoms');
        $this->addSql('ALTER TABLE film DROP surnoms');
        $this->addSql('ALTER TABLE anime DROP surnoms');
    }
}
