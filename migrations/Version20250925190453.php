<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250925190206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Supprime la contrainte de clé étrangère temporairement
        $this->addSql('ALTER TABLE card_anime DROP FOREIGN KEY FK_2B30A884794BBE89');

        // Applique les changements sur la table card_anime
        $this->addSql('ALTER TABLE card_anime CHANGE anime_id anime_id INT NOT NULL, CHANGE quantity quantity INT NOT NULL');

        // Supprime la colonne profile_image_path de la table user
        $this->addSql('ALTER TABLE user DROP profile_image_path');
        
        // Ajoute la contrainte de clé étrangère à nouveau
        $this->addSql('ALTER TABLE card_anime ADD CONSTRAINT FK_2B30A884794BBE89 FOREIGN KEY (anime_id) REFERENCES anime (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_anime CHANGE anime_id anime_id INT DEFAULT NULL, CHANGE quantity quantity INT DEFAULT 3 NOT NULL');
        $this->addSql('ALTER TABLE user ADD profile_image_path VARCHAR(255) DEFAULT NULL');
    }
}