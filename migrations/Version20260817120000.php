<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l identifiant Discord du joueur, utilise pour le mentionner lors d un drop.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD discord_id VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP discord_id');
    }
}
