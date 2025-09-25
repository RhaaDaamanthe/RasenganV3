<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924192054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_film (id INT AUTO_INCREMENT NOT NULL, rarity_id INT DEFAULT NULL, film_id INT DEFAULT NULL, nom VARCHAR(150) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, quantity INT NOT NULL, INDEX IDX_6E77BC80F3747573 (rarity_id), INDEX IDX_6E77BC80567F5183 (film_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE film (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_card_film (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, card_film_id INT DEFAULT NULL, quantity INT NOT NULL, INDEX IDX_6E973164A76ED395 (user_id), INDEX IDX_6E973164165DBF5 (card_film_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card_film ADD CONSTRAINT FK_6E77BC80F3747573 FOREIGN KEY (rarity_id) REFERENCES rarities (id)');
        $this->addSql('ALTER TABLE card_film ADD CONSTRAINT FK_6E77BC80567F5183 FOREIGN KEY (film_id) REFERENCES film (id)');
        $this->addSql('ALTER TABLE user_card_film ADD CONSTRAINT FK_6E973164A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_card_film ADD CONSTRAINT FK_6E973164165DBF5 FOREIGN KEY (card_film_id) REFERENCES card_film (id)');
        $this->addSql('ALTER TABLE card_anime CHANGE anime_id anime_id INT NOT NULL, CHANGE quantity quantity INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_film DROP FOREIGN KEY FK_6E77BC80F3747573');
        $this->addSql('ALTER TABLE card_film DROP FOREIGN KEY FK_6E77BC80567F5183');
        $this->addSql('ALTER TABLE user_card_film DROP FOREIGN KEY FK_6E973164A76ED395');
        $this->addSql('ALTER TABLE user_card_film DROP FOREIGN KEY FK_6E973164165DBF5');
        $this->addSql('DROP TABLE card_film');
        $this->addSql('DROP TABLE film');
        $this->addSql('DROP TABLE user_card_film');
        $this->addSql('ALTER TABLE card_anime CHANGE anime_id anime_id INT DEFAULT NULL, CHANGE quantity quantity INT DEFAULT 3 NOT NULL');
    }
}
