<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501400000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add navidrome_history table to cache the most recent Navidrome scrobbles snapshot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE navidrome_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                media_file_id VARCHAR(255) NOT NULL,
                played_at DATETIME NOT NULL,
                artist VARCHAR(255) NOT NULL,
                title VARCHAR(255) NOT NULL,
                album VARCHAR(255) DEFAULT NULL,
                fetched_at DATETIME NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_navidrome_history_played ON navidrome_history (played_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE navidrome_history');
    }
}
