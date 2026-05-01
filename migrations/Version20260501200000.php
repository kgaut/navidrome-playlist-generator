<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add run_history table to audit cron jobs (playlist runs, stats, lastfm import).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE run_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                type VARCHAR(32) NOT NULL,
                reference VARCHAR(255) NOT NULL,
                label VARCHAR(255) NOT NULL,
                status VARCHAR(16) NOT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME DEFAULT NULL,
                duration_ms INTEGER DEFAULT NULL,
                message CLOB DEFAULT NULL,
                metrics CLOB DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_run_history_type ON run_history (type)');
        $this->addSql('CREATE INDEX idx_run_history_status ON run_history (status)');
        $this->addSql('CREATE INDEX idx_run_history_started_at ON run_history (started_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE run_history');
    }
}
