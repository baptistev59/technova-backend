<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240209123000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_name = 'vendor'
    ) THEN
        ALTER TABLE vendor ADD COLUMN IF NOT EXISTS is_suspended BOOLEAN DEFAULT false NOT NULL;
    END IF;
END
$$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'vendor'
          AND column_name = 'is_suspended'
    ) THEN
        ALTER TABLE vendor DROP COLUMN is_suspended;
    END IF;
END
$$;
SQL);
    }
}
