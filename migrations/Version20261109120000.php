<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Éditeur de cartes jeu vidéo : numéro de carte et paramètres de composition (image source + cadrage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_jeu ADD numero INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card_jeu ADD composition JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_jeu DROP composition');
        $this->addSql('ALTER TABLE card_jeu DROP numero');
    }
}
