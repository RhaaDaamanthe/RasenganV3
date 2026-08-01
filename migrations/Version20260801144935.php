<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801144935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la rareté visuelle des badges et les nouveaux badges événements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE badge ADD rarity VARCHAR(20) NOT NULL DEFAULT 'commune'");

        // Paliers Collectionneur : I-III commune (déjà la valeur par défaut), IV-V rare, VI épique, VII légendaire
        $this->addSql("UPDATE badge SET rarity = 'rare' WHERE type = 'collectionneur' AND level IN (4, 5)");
        $this->addSql("UPDATE badge SET rarity = 'epique' WHERE type = 'collectionneur' AND level = 6");
        $this->addSql("UPDATE badge SET rarity = 'legendaire' WHERE type = 'collectionneur' AND level = 7");

        // Tous les badges événements en légendaire
        $this->addSql("UPDATE badge SET rarity = 'legendaire' WHERE type = 'event'");

        // Nouveaux badges événements
        $this->addSql("INSERT INTO badge (name, description, icon, type, level, objective, rarity) VALUES
            ('Halloween 2025', 'A participé aux événements d''Halloween 2025.', 'Halloween2025.png', 'event', 0, 0, 'legendaire'),
            ('Pirate 2026', 'A participé à l''événement Pirate 2026.', 'Pirate2026.png', 'event', 0, 0, 'legendaire'),
            ('Été 2025', 'A participé aux événements de l''été 2025.', 'Ete2025.png', 'event', 0, 0, 'legendaire'),
            ('Saint-Valentin 2025', 'A participé à l''événement Saint-Valentin 2025.', 'SaintValentin2025.png', 'event', 0, 0, 'legendaire')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM badge WHERE name IN ('Halloween 2025', 'Pirate 2026', 'Été 2025', 'Saint-Valentin 2025')");
        $this->addSql('ALTER TABLE badge DROP rarity');
    }
}
