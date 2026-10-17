<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la date d\'expiration des offres d\'échange (1 semaine par défaut)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE trade_offer ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
            $this->addSql('COMMENT ON COLUMN trade_offer.expires_at IS \'(DC2Type:datetime_immutable)\'');
            $this->addSql('UPDATE trade_offer SET expires_at = created_at + INTERVAL \'7 days\'');
            // Les offres déjà en attente repartent pour une semaine pleine plutôt que d'expirer d'un coup.
            $this->addSql('UPDATE trade_offer SET expires_at = NOW() + INTERVAL \'7 days\' WHERE status = \'pending\'');
            $this->addSql('ALTER TABLE trade_offer ALTER expires_at SET NOT NULL');

            return;
        }

        $this->addSql('ALTER TABLE trade_offer ADD expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE trade_offer SET expires_at = DATE_ADD(created_at, INTERVAL 7 DAY)');
        $this->addSql('UPDATE trade_offer SET expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE status = \'pending\'');
        $this->addSql('ALTER TABLE trade_offer MODIFY expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trade_offer DROP expires_at');
    }
}
