<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801171032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la date d\'obtention des cartes (historique des derniers drops)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_card_anime ADD obtained_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_card_film ADD obtained_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // Les lignes déjà existantes n'ont pas de vraie date d'obtention connue :
        // on les backfill à "maintenant" pour ne pas laisser de NULL.
        // UTC_TIMESTAMP() (et non NOW(), qui est l'heure locale du serveur MySQL) pour
        // rester cohérent avec \DateTimeImmutable côté PHP (date.timezone = UTC) : sinon
        // les nouveaux drops, datés en UTC, se retrouvent "dans le passé" par rapport à
        // ce backfill tant que l'horloge UTC n'a pas rattrapé le décalage local.
        $this->addSql('UPDATE user_card_anime SET obtained_at = UTC_TIMESTAMP() WHERE obtained_at IS NULL');
        $this->addSql('UPDATE user_card_film SET obtained_at = UTC_TIMESTAMP() WHERE obtained_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_card_film DROP obtained_at');
        $this->addSql('ALTER TABLE user_card_anime DROP obtained_at');
    }
}
