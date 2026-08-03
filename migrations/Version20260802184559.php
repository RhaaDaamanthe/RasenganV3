<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802184559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_wishlist_anime (user_id INT NOT NULL, card_anime_id INT NOT NULL, INDEX IDX_2C24EEE3A76ED395 (user_id), INDEX IDX_2C24EEE3C07A700A (card_anime_id), PRIMARY KEY(user_id, card_anime_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_wishlist_film (user_id INT NOT NULL, card_film_id INT NOT NULL, INDEX IDX_17F5C284A76ED395 (user_id), INDEX IDX_17F5C284165DBF5 (card_film_id), PRIMARY KEY(user_id, card_film_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_wishlist_anime ADD CONSTRAINT FK_2C24EEE3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_wishlist_anime ADD CONSTRAINT FK_2C24EEE3C07A700A FOREIGN KEY (card_anime_id) REFERENCES card_anime (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_wishlist_film ADD CONSTRAINT FK_17F5C284A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_wishlist_film ADD CONSTRAINT FK_17F5C284165DBF5 FOREIGN KEY (card_film_id) REFERENCES card_film (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_wishlist_anime DROP FOREIGN KEY FK_2C24EEE3A76ED395');
        $this->addSql('ALTER TABLE user_wishlist_anime DROP FOREIGN KEY FK_2C24EEE3C07A700A');
        $this->addSql('ALTER TABLE user_wishlist_film DROP FOREIGN KEY FK_17F5C284A76ED395');
        $this->addSql('ALTER TABLE user_wishlist_film DROP FOREIGN KEY FK_17F5C284165DBF5');
        $this->addSql('DROP TABLE user_wishlist_anime');
        $this->addSql('DROP TABLE user_wishlist_film');
    }
}
