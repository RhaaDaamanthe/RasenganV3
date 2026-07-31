<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731203250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_anime_requirement (id INT AUTO_INCREMENT NOT NULL, mythic_card_id INT NOT NULL, required_card_id INT NOT NULL, quantity_required INT NOT NULL, INDEX IDX_C5AA767A91C0BC0 (mythic_card_id), INDEX IDX_C5AA767A6B2DBDE0 (required_card_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card_film_requirement (id INT AUTO_INCREMENT NOT NULL, mythic_card_id INT NOT NULL, required_card_id INT NOT NULL, quantity_required INT NOT NULL, INDEX IDX_30E0A0DD91C0BC0 (mythic_card_id), INDEX IDX_30E0A0DD6B2DBDE0 (required_card_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_anime_requirement ADD CONSTRAINT FK_C5AA767A91C0BC0 FOREIGN KEY (mythic_card_id) REFERENCES card_anime (id)');
        $this->addSql('ALTER TABLE card_anime_requirement ADD CONSTRAINT FK_C5AA767A6B2DBDE0 FOREIGN KEY (required_card_id) REFERENCES card_anime (id)');
        $this->addSql('ALTER TABLE card_film_requirement ADD CONSTRAINT FK_30E0A0DD91C0BC0 FOREIGN KEY (mythic_card_id) REFERENCES card_film (id)');
        $this->addSql('ALTER TABLE card_film_requirement ADD CONSTRAINT FK_30E0A0DD6B2DBDE0 FOREIGN KEY (required_card_id) REFERENCES card_film (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_anime_requirement DROP FOREIGN KEY FK_C5AA767A91C0BC0');
        $this->addSql('ALTER TABLE card_anime_requirement DROP FOREIGN KEY FK_C5AA767A6B2DBDE0');
        $this->addSql('ALTER TABLE card_film_requirement DROP FOREIGN KEY FK_30E0A0DD91C0BC0');
        $this->addSql('ALTER TABLE card_film_requirement DROP FOREIGN KEY FK_30E0A0DD6B2DBDE0');
        $this->addSql('DROP TABLE card_anime_requirement');
        $this->addSql('DROP TABLE card_film_requirement');
    }
}
