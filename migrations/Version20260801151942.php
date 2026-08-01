<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801151942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un ordre d\'affichage explicite aux badges et le badge Noël 2024';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE badge ADD position INT NOT NULL DEFAULT 0, CHANGE rarity rarity VARCHAR(20) NOT NULL");

        // Paliers Collectionneur, dans l'ordre des niveaux
        $this->addSql("UPDATE badge SET position = level * 10 WHERE type = 'collectionneur'");

        // Anniversaire (badge permanent, non daté) avant les badges saisonniers
        $this->addSql("UPDATE badge SET position = 80 WHERE name = 'Anniversaire'");

        // Renomme l'icône de Noël 2025 pour la distinguer de Noël 2024
        $this->addSql("UPDATE badge SET icon = 'Noel2025.png' WHERE name = 'Noël 2025'");

        // Ordre chronologique des badges événements
        $this->addSql("UPDATE badge SET position = 100 WHERE name = 'Saint-Valentin 2025'");
        $this->addSql("UPDATE badge SET position = 110 WHERE name = 'Été 2025'");
        $this->addSql("UPDATE badge SET position = 120 WHERE name = 'Halloween 2025'");
        $this->addSql("UPDATE badge SET position = 130 WHERE name = 'Noël 2025'");
        $this->addSql("UPDATE badge SET position = 140 WHERE name = 'Pirate 2026'");

        // Nouveau badge : Noël 2024
        $this->addSql("INSERT INTO badge (name, description, icon, type, level, objective, rarity, position) VALUES
            ('Noël 2024', 'A participé au calendrier de l''Avent 2024.', 'Noel2024.png', 'event', 0, 0, 'legendaire', 90)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM badge WHERE name = 'Noël 2024'");
        $this->addSql("UPDATE badge SET icon = 'Noel.png' WHERE name = 'Noël 2025'");
        $this->addSql('ALTER TABLE badge DROP position, CHANGE rarity rarity VARCHAR(20) DEFAULT \'commune\' NOT NULL');
    }
}
