<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: playlist_definition + setting tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE playlist_definition (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                generator_key VARCHAR(64) NOT NULL,
                parameters CLOB NOT NULL,
                limit_override INTEGER DEFAULT NULL,
                playlist_name_template VARCHAR(255) DEFAULT NULL,
                schedule VARCHAR(100) DEFAULT NULL,
                enabled BOOLEAN NOT NULL DEFAULT 1,
                replace_existing BOOLEAN NOT NULL DEFAULT 1,
                last_run_at DATETIME DEFAULT NULL,
                last_run_status VARCHAR(32) DEFAULT NULL,
                last_run_message CLOB DEFAULT NULL,
                last_subsonic_playlist_id VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_playlist_definition_name ON playlist_definition (name)');

        $this->addSql(<<<'SQL'
            CREATE TABLE setting (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                "key" VARCHAR(64) NOT NULL,
                value CLOB NOT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_setting_key ON setting ("key")');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE playlist_definition');
        $this->addSql('DROP TABLE setting');
    }
}
