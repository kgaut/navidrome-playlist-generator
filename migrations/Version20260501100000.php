<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stats_snapshot table to cache stats per period';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE stats_snapshot (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                period VARCHAR(32) NOT NULL,
                data CLOB NOT NULL,
                computed_at DATETIME NOT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_stats_snapshot_period ON stats_snapshot (period)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stats_snapshot');
    }
}
