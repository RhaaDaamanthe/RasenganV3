<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017120000 extends AbstractMigration
{
    /** table => colonne de la carte */
    private const TABLES = [
        'user_card_anime' => 'card_anime_id',
        'user_card_film' => 'card_film_id',
        'user_card_jeu' => 'card_jeu_id',
    ];

    public function getDescription(): string
    {
        return 'Une seule ligne de possession par joueur et par carte (fusion des doublons existants puis index unique)';
    }

    public function up(Schema $schema): void
    {
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        foreach (self::TABLES as $table => $cardColumn) {
            // Doublons éventuels : on garde la plus ancienne ligne, qui reçoit la somme des
            // exemplaires et la date d'obtention la plus récente, puis on supprime les autres.
            $doublons = "SELECT MIN(id) AS keep_id, SUM(quantity) AS total, MAX(obtained_at) AS last_obtained
                FROM $table GROUP BY user_id, $cardColumn HAVING COUNT(*) > 1";

            if ($postgres) {
                $this->addSql("UPDATE $table k SET quantity = d.total, obtained_at = d.last_obtained
                    FROM ($doublons) d WHERE k.id = d.keep_id");
                $this->addSql("DELETE FROM $table a USING $table b
                    WHERE a.user_id = b.user_id AND a.$cardColumn = b.$cardColumn AND a.id > b.id");
            } else {
                $this->addSql("UPDATE $table k INNER JOIN ($doublons) d ON k.id = d.keep_id
                    SET k.quantity = d.total, k.obtained_at = d.last_obtained");
                $this->addSql("DELETE a FROM $table a INNER JOIN $table b
                    ON a.user_id = b.user_id AND a.$cardColumn = b.$cardColumn AND a.id > b.id");
            }

            $this->addSql("CREATE UNIQUE INDEX uniq_$table ON $table (user_id, $cardColumn)");
        }
    }

    public function down(Schema $schema): void
    {
        $postgres = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        foreach (array_keys(self::TABLES) as $table) {
            $this->addSql($postgres ? "DROP INDEX uniq_$table" : "DROP INDEX uniq_$table ON $table");
        }
    }
}
