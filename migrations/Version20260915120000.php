<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les emplacements de cartes de la vitrine des joueurs';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE user_showcase_card (id SERIAL NOT NULL, user_id INT NOT NULL, card_anime_id INT DEFAULT NULL, card_film_id INT DEFAULT NULL, card_jeu_id INT DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX IDX_SHOWCASE_USER ON user_showcase_card (user_id)');
            $this->addSql('CREATE INDEX IDX_SHOWCASE_ANIME ON user_showcase_card (card_anime_id)');
            $this->addSql('CREATE INDEX IDX_SHOWCASE_FILM ON user_showcase_card (card_film_id)');
            $this->addSql('CREATE INDEX IDX_SHOWCASE_JEU ON user_showcase_card (card_jeu_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_SHOWCASE_USER_POSITION ON user_showcase_card (user_id, position)');
            $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_ANIME FOREIGN KEY (card_anime_id) REFERENCES card_anime (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_FILM FOREIGN KEY (card_film_id) REFERENCES card_film (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_JEU FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

            return;
        }

        $this->addSql('CREATE TABLE user_showcase_card (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, card_anime_id INT DEFAULT NULL, card_film_id INT DEFAULT NULL, card_jeu_id INT DEFAULT NULL, position INT NOT NULL, INDEX IDX_SHOWCASE_USER (user_id), INDEX IDX_SHOWCASE_ANIME (card_anime_id), INDEX IDX_SHOWCASE_FILM (card_film_id), INDEX IDX_SHOWCASE_JEU (card_jeu_id), UNIQUE INDEX UNIQ_SHOWCASE_USER_POSITION (user_id, position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_ANIME FOREIGN KEY (card_anime_id) REFERENCES card_anime (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_FILM FOREIGN KEY (card_film_id) REFERENCES card_film (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_showcase_card ADD CONSTRAINT FK_SHOWCASE_JEU FOREIGN KEY (card_jeu_id) REFERENCES card_jeu (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_showcase_card');
    }
}
