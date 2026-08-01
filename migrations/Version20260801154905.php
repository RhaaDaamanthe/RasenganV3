<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801154905 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace le badge Anniversaire par 7 paliers Ancienneté';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE badge CHANGE position position INT NOT NULL');

        // Retire le badge Anniversaire (remplacé par les paliers Ancienneté)
        $this->addSql("DELETE FROM user_badge WHERE badge_id = (SELECT id FROM badge WHERE name = 'Anniversaire')");
        $this->addSql("DELETE FROM badge WHERE name = 'Anniversaire'");

        // 7 paliers Ancienneté, calculés en jours depuis l'inscription
        $this->addSql("INSERT INTO badge (name, description, icon, type, level, objective, rarity, position) VALUES
            ('Ancienneté I', 'Membre depuis 1 mois.', 'Anciennete1.png', 'anciennete', 1, 30, 'commune', 71),
            ('Ancienneté II', 'Membre depuis 6 mois.', 'Anciennete2.png', 'anciennete', 2, 182, 'commune', 72),
            ('Ancienneté III', 'Membre depuis 1 an.', 'Anciennete3.png', 'anciennete', 3, 365, 'rare', 73),
            ('Ancienneté IV', 'Membre depuis 2 ans.', 'Anciennete4.png', 'anciennete', 4, 730, 'rare', 74),
            ('Ancienneté V', 'Membre depuis 3 ans.', 'Anciennete5.png', 'anciennete', 5, 1095, 'epique', 75),
            ('Ancienneté VI', 'Membre depuis 5 ans.', 'Anciennete6.png', 'anciennete', 6, 1825, 'epique', 76),
            ('Ancienneté VII', 'Membre depuis 10 ans.', 'Anciennete7.png', 'anciennete', 7, 3650, 'legendaire', 77)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM badge WHERE type = 'anciennete'");
        $this->addSql("INSERT INTO badge (name, description, icon, type, level, objective, rarity, position) VALUES
            ('Anniversaire', 'A fêté son anniversaire sur le jeu.', 'anniversaire.png', 'event', 0, 0, 'legendaire', 80)");
        $this->addSql('ALTER TABLE badge CHANGE position position INT DEFAULT 0 NOT NULL');
    }
}
