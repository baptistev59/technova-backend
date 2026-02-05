<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205034344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove TaxZone entity and refactor ProductTaxZone to store country codes directly';
    }

    public function up(Schema $schema): void
    {
        // 1. Drop Product.tax_zone_id relation (legacy)
        $this->addSql('ALTER TABLE product DROP CONSTRAINT IF EXISTS fk_product_tax_zone');
        $this->addSql('DROP INDEX IF EXISTS idx_d34a04adde649fa1');
        $this->addSql('ALTER TABLE product DROP COLUMN IF EXISTS tax_zone_id');

        // 2. Add country_codes to ProductTaxZone (replaces TaxZone relation)
        $this->addSql('ALTER TABLE product_tax_zone ADD COLUMN country_codes JSONB DEFAULT \'[]\'');
        
        // 3. Migrate data: copy TaxZone.country_codes to ProductTaxZone
        $this->addSql('
            UPDATE product_tax_zone ptz
            SET country_codes = tz.country_codes
            FROM tax_zone tz
            WHERE ptz.tax_zone_id = tz.id
        ');

        // 4. Drop ProductTaxZone.tax_zone_id relation
        $this->addSql('ALTER TABLE product_tax_zone DROP CONSTRAINT IF EXISTS fk_product_tax_zone_tax_zone');
        $this->addSql('DROP INDEX IF EXISTS idx_product_tax_zone_tax_zone');
        $this->addSql('DROP INDEX IF EXISTS idx_77d4a568de649fa1');
        $this->addSql('ALTER TABLE product_tax_zone DROP COLUMN tax_zone_id');

        // 5. Drop TaxZone table completely
        $this->addSql('DROP TABLE IF EXISTS tax_zone CASCADE');

        // 6. Adjust ProductTaxZone constraints
        $this->addSql('ALTER TABLE product_tax_zone ALTER tax_class TYPE VARCHAR(32)');
        $this->addSql('ALTER TABLE product_tax_zone ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE product_tax_zone ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN product_tax_zone.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_tax_zone.updated_at IS \'(DC2Type:datetime_immutable)\'');
        
        // 7. Update indexes
        $this->addSql('DROP INDEX IF EXISTS uniq_product_tax_zone');
        $this->addSql('DROP INDEX IF EXISTS uniq_product_tax_zone_product_zone');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_tax_zone_product ON product_tax_zone (product_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_tax_zone_countries ON product_tax_zone USING GIN (country_codes)');
    }

    public function down(Schema $schema): void
    {
        // Rollback not supported - TaxZone data would be lost
        $this->throwIrreversibleMigrationException('Cannot rollback TaxZone removal - data migration is one-way');
    }
}
