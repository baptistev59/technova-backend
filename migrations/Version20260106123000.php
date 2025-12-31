<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260106123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update product reviews for half-star ratings and moderation flag.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_review ALTER COLUMN rating TYPE NUMERIC(2,1) USING rating::numeric');
        $this->addSql('ALTER TABLE product_review ADD approved BOOLEAN DEFAULT TRUE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_review DROP COLUMN approved');
        $this->addSql('ALTER TABLE product_review ALTER COLUMN rating TYPE SMALLINT USING rating::smallint');
    }
}
