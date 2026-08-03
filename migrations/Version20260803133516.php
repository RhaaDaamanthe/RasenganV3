<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260803133516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les cartes alternatives acceptées pour valider un ingrédient de combinaison mythique';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_anime_requirement_alternative (card_anime_requirement_id INT NOT NULL, card_anime_id INT NOT NULL, INDEX IDX_C482C23E7041BCF5 (card_anime_requirement_id), INDEX IDX_C482C23EC07A700A (card_anime_id), PRIMARY KEY(card_anime_requirement_id, card_anime_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card_film_requirement_alternative (card_film_requirement_id INT NOT NULL, card_film_id INT NOT NULL, INDEX IDX_7AFFACEDED1D3DF2 (card_film_requirement_id), INDEX IDX_7AFFACED165DBF5 (card_film_id), PRIMARY KEY(card_film_requirement_id, card_film_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_anime_requirement_alternative ADD CONSTRAINT FK_C482C23E7041BCF5 FOREIGN KEY (card_anime_requirement_id) REFERENCES card_anime_requirement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_anime_requirement_alternative ADD CONSTRAINT FK_C482C23EC07A700A FOREIGN KEY (card_anime_id) REFERENCES card_anime (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_film_requirement_alternative ADD CONSTRAINT FK_7AFFACEDED1D3DF2 FOREIGN KEY (card_film_requirement_id) REFERENCES card_film_requirement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_film_requirement_alternative ADD CONSTRAINT FK_7AFFACED165DBF5 FOREIGN KEY (card_film_id) REFERENCES card_film (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_anime_requirement CHANGE placeholder_nom placeholder_nom VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE card_film_requirement CHANGE placeholder_nom placeholder_nom VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_anime_requirement_alternative DROP FOREIGN KEY FK_C482C23E7041BCF5');
        $this->addSql('ALTER TABLE card_anime_requirement_alternative DROP FOREIGN KEY FK_C482C23EC07A700A');
        $this->addSql('ALTER TABLE card_film_requirement_alternative DROP FOREIGN KEY FK_7AFFACEDED1D3DF2');
        $this->addSql('ALTER TABLE card_film_requirement_alternative DROP FOREIGN KEY FK_7AFFACED165DBF5');
        $this->addSql('DROP TABLE card_anime_requirement_alternative');
        $this->addSql('DROP TABLE card_film_requirement_alternative');
        $this->addSql('ALTER TABLE card_film_requirement CHANGE placeholder_nom placeholder_nom VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE card_anime_requirement CHANGE placeholder_nom placeholder_nom VARCHAR(150) DEFAULT NULL');
    }
}
