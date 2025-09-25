<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924111459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE anime (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card_anime (id INT AUTO_INCREMENT NOT NULL, rarity_id INT NOT NULL, anime_id INT NOT NULL, nom VARCHAR(150) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_2B30A884F3747573 (rarity_id), INDEX IDX_2B30A884794BBE89 (anime_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rarities (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(30) NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_anime ADD CONSTRAINT FK_2B30A884F3747573 FOREIGN KEY (rarity_id) REFERENCES rarities (id)');
        $this->addSql('ALTER TABLE card_anime ADD CONSTRAINT FK_2B30A884794BBE89 FOREIGN KEY (anime_id) REFERENCES anime (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_anime DROP FOREIGN KEY FK_2B30A884F3747573');
        $this->addSql('ALTER TABLE card_anime DROP FOREIGN KEY FK_2B30A884794BBE89');
        $this->addSql('DROP TABLE anime');
        $this->addSql('DROP TABLE card_anime');
        $this->addSql('DROP TABLE rarities');
    }
}
