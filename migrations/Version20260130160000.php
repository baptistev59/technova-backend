<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FK product.tax_zone_id -> tax_zone.id (after tax_zone creation)';
    }

    public function up(Schema $schema): void
    {
        // Check if constraint already exists
        $this->addSql("
            DO $$ 
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints 
                    WHERE constraint_name = 'fk_product_tax_zone' 
                    AND table_name = 'product'
                ) THEN
                    ALTER TABLE product ADD CONSTRAINT fk_product_tax_zone FOREIGN KEY (tax_zone_id) REFERENCES tax_zone (id) ON DELETE SET NULL;
                END IF;
            END $$
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT IF EXISTS fk_product_tax_zone');
    }
}
