<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lastfm_history table to cache the most recent Last.fm scrobbles per user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE lastfm_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                lastfm_user VARCHAR(255) NOT NULL,
                played_at DATETIME NOT NULL,
                artist VARCHAR(255) NOT NULL,
                title VARCHAR(255) NOT NULL,
                album VARCHAR(255) DEFAULT NULL,
                mbid VARCHAR(64) DEFAULT NULL,
                fetched_at DATETIME NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_lastfm_history_user_played ON lastfm_history (lastfm_user, played_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lastfm_history');
    }
}
