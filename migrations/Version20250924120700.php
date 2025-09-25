<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924120700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_card_anime (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, card_anime_id INT NOT NULL, quantity INT NOT NULL, INDEX IDX_8C576E68A76ED395 (user_id), INDEX IDX_8C576E68C07A700A (card_anime_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_card_anime ADD CONSTRAINT FK_8C576E68A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_card_anime ADD CONSTRAINT FK_8C576E68C07A700A FOREIGN KEY (card_anime_id) REFERENCES card_anime (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_card_anime DROP FOREIGN KEY FK_8C576E68A76ED395');
        $this->addSql('ALTER TABLE user_card_anime DROP FOREIGN KEY FK_8C576E68C07A700A');
        $this->addSql('DROP TABLE user_card_anime');
    }
}
