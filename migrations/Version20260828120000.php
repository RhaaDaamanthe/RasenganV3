<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Section « jeux vidéo » : mêmes tables que les animés et les films
 * (jeu / card_jeu / user_card_jeu / recettes mythiques / wishlist), plus la
 * colonne card_jeu_id des lignes d'échange.
 *
 * Les migrations existantes du projet sont écrites en SQL MySQL alors que
 * DATABASE_URL peut pointer sur PostgreSQL : les deux dialectes sont donc émis.
 * Les noms d'index et de contraintes sont ceux générés par Doctrine, pour qu'un
 * futur doctrine:migrations:diff ne propose pas de les renommer.
 */
final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tables de la section jeux vidéo (jeu, card_jeu, user_card_jeu, recettes mythiques, wishlist)';
    }

    public function up(Schema $schema): void
    {
        if ($this->isPostgres()) {
            $this->upPostgres();

            return;
        }

        $this->upMySql();
    }

    public function down(Schema $schema): void
    {
        if ($this->isPostgres()) {
            $this->addSql('ALTER TABLE trade_offer_item DROP CONSTRAINT FK_CF7050E4FEA16A5A');
            $this->addSql('DROP INDEX IDX_CF7050E4FEA16A5A');
            $this->addSql('ALTER TABLE trade_offer_item DROP card_jeu_id');
            $this->addSql('DROP TABLE user_wishlist_jeu');
            $this->addSql('DROP TABLE card_jeu_requirement_alternative');
            $this->addSql('DROP TABLE card_jeu_requirement');
            $this->addSql('DROP TABLE user_card_jeu');
            $this->addSql('DROP TABLE card_jeu');
            $this->addSql('DROP TABLE jeu');

            return;
        }

        $this->addSql('ALTER TABLE trade_offer_item DROP FOREIGN KEY FK_CF7050E4FEA16A5A');
        $this->addSql('DROP INDEX IDX_CF7050E4FEA16A5A ON trade_offer_item');
        $this->addSql('ALTER TABLE trade_offer_item DROP card_jeu_id');
        $this->addSql('DROP TABLE user_wishlist_jeu');
        $this->addSql('DROP TABLE card_jeu_requirement_alternative');
        $this->addSql('DROP TABLE card_jeu_requirement');
        $this->addSql('DROP TABLE user_card_jeu');
        $this->addSql('DROP TABLE card_jeu');
        $this->addSql('DROP TABLE jeu');
    }

    private function isPostgres(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }

    private function upMySql(): void
    {
        $opts = ' DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';

        $this->addSql('CREATE TABLE jeu (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, PRIMARY KEY(id))'.$opts);
        $this->addSql('CREATE TABLE card_jeu (id INT AUTO_INCREMENT NOT NULL, rarity_id INT DEFAULT NULL, jeu_id INT DEFAULT NULL, nom VARCHAR(150) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, quantity INT NOT NULL, INDEX IDX_D2140974F3747573 (rarity_id), INDEX IDX_D21409748C9E392E (jeu_id), PRIMARY KEY(id))'.$opts);
        $this->addSql('CREATE TABLE user_card_jeu (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, card_jeu_id INT DEFAULT NULL, quantity INT NOT NULL, obtained_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3299ED74A76ED395 (user_id), INDEX IDX_3299ED74FEA16A5A (card_jeu_id), PRIMARY KEY(id))'.$opts);
        $this->addSql('CREATE TABLE card_jeu_requirement (id INT AUTO_INCREMENT NOT NULL, mythic_card_id INT NOT NULL, required_card_id INT DEFAULT NULL, placeholder_nom VARCHAR(255) DEFAULT NULL, quantity_required INT NOT NULL, INDEX IDX_CD372B8D91C0BC0 (mythic_card_id), INDEX IDX_CD372B8D6B2DBDE0 (required_card_id), PRIMARY KEY(id))'.$opts);
        $this->addSql('CREATE TABLE card_jeu_requirement_alternative (card_jeu_requirement_id INT NOT NULL, card_jeu_id INT NOT NULL, INDEX IDX_C830F4C741611548 (card_jeu_requirement_id), INDEX IDX_C830F4C7FEA16A5A (card_jeu_id), PRIMARY KEY(card_jeu_requirement_id, card_jeu_id))'.$opts);
        $this->addSql('CREATE TABLE user_wishlist_jeu (user_id INT NOT NULL, card_jeu_id INT NOT NULL, INDEX IDX_8CD2A97DA76ED395 (user_id), INDEX IDX_8CD2A97DFEA16A5A (card_jeu_id), PRIMARY KEY(user_id, card_jeu_id))'.$opts);

        $this->addSql('ALTER TABLE card_jeu ADD CONSTRAINT FK_D2140974F3747573 FOREIGN KEY (rarity_id) REFERENCES rarities (id)');
        $this->addSql('ALTER TABLE card_jeu ADD CONSTRAINT FK_D21409748C9E392E FOREIGN KEY (jeu_id) REFERENCES jeu (id)');
        $this->addSql('ALTER TABLE user_card_jeu ADD CONSTRAINT FK_3299ED74A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_card_jeu ADD CONSTRAINT FK_3299ED74FEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id)');
        $this->addSql('ALTER TABLE card_jeu_requirement ADD CONSTRAINT FK_CD372B8D91C0BC0 FOREIGN KEY (mythic_card_id) REFERENCES card_jeu (id)');
        $this->addSql('ALTER TABLE card_jeu_requirement ADD CONSTRAINT FK_CD372B8D6B2DBDE0 FOREIGN KEY (required_card_id) REFERENCES card_jeu (id)');
        $this->addSql('ALTER TABLE card_jeu_requirement_alternative ADD CONSTRAINT FK_C830F4C741611548 FOREIGN KEY (card_jeu_requirement_id) REFERENCES card_jeu_requirement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_jeu_requirement_alternative ADD CONSTRAINT FK_C830F4C7FEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_wishlist_jeu ADD CONSTRAINT FK_8CD2A97DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_wishlist_jeu ADD CONSTRAINT FK_8CD2A97DFEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE trade_offer_item ADD card_jeu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE trade_offer_item ADD CONSTRAINT FK_CF7050E4FEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id)');
        $this->addSql('CREATE INDEX IDX_CF7050E4FEA16A5A ON trade_offer_item (card_jeu_id)');
    }

    private function upPostgres(): void
    {
        $this->addSql('CREATE TABLE jeu (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE card_jeu (id SERIAL NOT NULL, rarity_id INT DEFAULT NULL, jeu_id INT DEFAULT NULL, nom VARCHAR(150) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D2140974F3747573 ON card_jeu (rarity_id)');
        $this->addSql('CREATE INDEX IDX_D21409748C9E392E ON card_jeu (jeu_id)');

        $this->addSql('CREATE TABLE user_card_jeu (id SERIAL NOT NULL, user_id INT DEFAULT NULL, card_jeu_id INT DEFAULT NULL, quantity INT NOT NULL, obtained_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3299ED74A76ED395 ON user_card_jeu (user_id)');
        $this->addSql('CREATE INDEX IDX_3299ED74FEA16A5A ON user_card_jeu (card_jeu_id)');
        $this->addSql('COMMENT ON COLUMN user_card_jeu.obtained_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE card_jeu_requirement (id SERIAL NOT NULL, mythic_card_id INT NOT NULL, required_card_id INT DEFAULT NULL, placeholder_nom VARCHAR(255) DEFAULT NULL, quantity_required INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CD372B8D91C0BC0 ON card_jeu_requirement (mythic_card_id)');
        $this->addSql('CREATE INDEX IDX_CD372B8D6B2DBDE0 ON card_jeu_requirement (required_card_id)');

        $this->addSql('CREATE TABLE card_jeu_requirement_alternative (card_jeu_requirement_id INT NOT NULL, card_jeu_id INT NOT NULL, PRIMARY KEY(card_jeu_requirement_id, card_jeu_id))');
        $this->addSql('CREATE INDEX IDX_C830F4C741611548 ON card_jeu_requirement_alternative (card_jeu_requirement_id)');
        $this->addSql('CREATE INDEX IDX_C830F4C7FEA16A5A ON card_jeu_requirement_alternative (card_jeu_id)');

        $this->addSql('CREATE TABLE user_wishlist_jeu (user_id INT NOT NULL, card_jeu_id INT NOT NULL, PRIMARY KEY(user_id, card_jeu_id))');
        $this->addSql('CREATE INDEX IDX_8CD2A97DA76ED395 ON user_wishlist_jeu (user_id)');
        $this->addSql('CREATE INDEX IDX_8CD2A97DFEA16A5A ON user_wishlist_jeu (card_jeu_id)');

        $this->addSql('ALTER TABLE card_jeu ADD CONSTRAINT FK_D2140974F3747573 FOREIGN KEY (rarity_id) REFERENCES rarities (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card_jeu ADD CONSTRAINT FK_D21409748C9E392E FOREIGN KEY (jeu_id) REFERENCES jeu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_card_jeu ADD CONSTRAINT FK_3299ED74A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_card_jeu ADD CONSTRAINT FK_3299ED74FEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card_jeu_requirement ADD CONSTRAINT FK_CD372B8D91C0BC0 FOREIGN KEY (mythic_card_id) REFERENCES card_jeu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card_jeu_requirement ADD CONSTRAINT FK_CD372B8D6B2DBDE0 FOREIGN KEY (required_card_id) REFERENCES card_jeu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card_jeu_requirement_alternative ADD CONSTRAINT FK_C830F4C741611548 FOREIGN KEY (card_jeu_requirement_id) REFERENCES card_jeu_requirement (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card_jeu_requirement_alternative ADD CONSTRAINT FK_C830F4C7FEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_wishlist_jeu ADD CONSTRAINT FK_8CD2A97DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_wishlist_jeu ADD CONSTRAINT FK_8CD2A97DFEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE trade_offer_item ADD card_jeu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE trade_offer_item ADD CONSTRAINT FK_CF7050E4FEA16A5A FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_CF7050E4FEA16A5A ON trade_offer_item (card_jeu_id)');
    }
}
