<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les tables du système d'échange entre joueurs (trade_offer / trade_offer_item).
 */
final class Version20260805074944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tables trade_offer et trade_offer_item pour les échanges entre joueurs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE trade_offer (id INT AUTO_INCREMENT NOT NULL, proposer_id INT NOT NULL, recipient_id INT NOT NULL, parent_offer_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3DB57DDB13FA634 (proposer_id), INDEX IDX_3DB57DDE92F8F78 (recipient_id), INDEX IDX_3DB57DDCC0BD83F (parent_offer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE trade_offer_item (id INT AUTO_INCREMENT NOT NULL, trade_offer_id INT NOT NULL, owner_id INT NOT NULL, card_anime_id INT DEFAULT NULL, card_film_id INT DEFAULT NULL, quantity INT NOT NULL, INDEX IDX_CF7050E47EBA39F (trade_offer_id), INDEX IDX_CF7050E47E3C61F9 (owner_id), INDEX IDX_CF7050E4C07A700A (card_anime_id), INDEX IDX_CF7050E4165DBF5 (card_film_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DDB13FA634 FOREIGN KEY (proposer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DDE92F8F78 FOREIGN KEY (recipient_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DDCC0BD83F FOREIGN KEY (parent_offer_id) REFERENCES trade_offer (id)');
        $this->addSql('ALTER TABLE trade_offer_item ADD CONSTRAINT FK_CF7050E47EBA39F FOREIGN KEY (trade_offer_id) REFERENCES trade_offer (id)');
        $this->addSql('ALTER TABLE trade_offer_item ADD CONSTRAINT FK_CF7050E47E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE trade_offer_item ADD CONSTRAINT FK_CF7050E4C07A700A FOREIGN KEY (card_anime_id) REFERENCES card_anime (id)');
        $this->addSql('ALTER TABLE trade_offer_item ADD CONSTRAINT FK_CF7050E4165DBF5 FOREIGN KEY (card_film_id) REFERENCES card_film (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DDB13FA634');
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DDE92F8F78');
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DDCC0BD83F');
        $this->addSql('ALTER TABLE trade_offer_item DROP FOREIGN KEY FK_CF7050E47EBA39F');
        $this->addSql('ALTER TABLE trade_offer_item DROP FOREIGN KEY FK_CF7050E47E3C61F9');
        $this->addSql('ALTER TABLE trade_offer_item DROP FOREIGN KEY FK_CF7050E4C07A700A');
        $this->addSql('ALTER TABLE trade_offer_item DROP FOREIGN KEY FK_CF7050E4165DBF5');
        $this->addSql('DROP TABLE trade_offer');
        $this->addSql('DROP TABLE trade_offer_item');
    }
}
