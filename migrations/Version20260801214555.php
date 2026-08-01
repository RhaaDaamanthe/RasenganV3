<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801214555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_anime_requirement ADD placeholder_nom VARCHAR(150) DEFAULT NULL, CHANGE required_card_id required_card_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card_film_requirement ADD placeholder_nom VARCHAR(150) DEFAULT NULL, CHANGE required_card_id required_card_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_anime_requirement DROP placeholder_nom, CHANGE required_card_id required_card_id INT NOT NULL');
        $this->addSql('ALTER TABLE card_film_requirement DROP placeholder_nom, CHANGE required_card_id required_card_id INT NOT NULL');
    }
}
