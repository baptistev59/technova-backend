<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260201154314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_vat_rate (id SERIAL NOT NULL, product_id INT NOT NULL, shop_id INT DEFAULT NULL, country_code VARCHAR(2) NOT NULL, tax_class VARCHAR(32) NOT NULL, rate NUMERIC(5, 2) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3D349D504584665A ON product_vat_rate (product_id)');
        $this->addSql('CREATE INDEX IDX_3D349D504D16C4DD ON product_vat_rate (shop_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_vat_rate_product_country_shop ON product_vat_rate (product_id, country_code, shop_id)');
        $this->addSql('COMMENT ON COLUMN product_vat_rate.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE product_vat_rate ADD CONSTRAINT FK_3D349D504584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_vat_rate ADD CONSTRAINT FK_3D349D504D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_external_image_error_last_seen');
        $this->addSql('ALTER TABLE external_image_error ALTER first_seen TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN external_image_error.first_seen IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX uniq_external_image_error_url RENAME TO UNIQ_225F75AFF47645AE');
        $this->addSql('ALTER INDEX idx_product_tax_zone RENAME TO IDX_D34A04ADDE649FA1');
        $this->addSql('DROP INDEX idx_tax_zone_is_preset');
        $this->addSql('ALTER TABLE tax_zone ALTER tax_class DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_zone ALTER rate DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_zone ALTER is_preset DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_zone ALTER sort_order DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_zone ALTER active DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_zone ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tax_zone.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_tax_zone_shop RENAME TO IDX_BC64CFFF4D16C4DD');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE product_vat_rate DROP CONSTRAINT FK_3D349D504584665A');
        $this->addSql('ALTER TABLE product_vat_rate DROP CONSTRAINT FK_3D349D504D16C4DD');
        $this->addSql('DROP TABLE product_vat_rate');
        $this->addSql('ALTER TABLE tax_zone ALTER tax_class SET DEFAULT \'STANDARD\'');
        $this->addSql('ALTER TABLE tax_zone ALTER rate SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE tax_zone ALTER is_preset SET DEFAULT false');
        $this->addSql('ALTER TABLE tax_zone ALTER sort_order SET DEFAULT 999');
        $this->addSql('ALTER TABLE tax_zone ALTER active SET DEFAULT true');
        $this->addSql('ALTER TABLE tax_zone ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN tax_zone.created_at IS NULL');
        $this->addSql('CREATE INDEX idx_tax_zone_is_preset ON tax_zone (is_preset)');
        $this->addSql('ALTER INDEX idx_bc64cfff4d16c4dd RENAME TO idx_tax_zone_shop');
        $this->addSql('ALTER INDEX idx_d34a04adde649fa1 RENAME TO idx_product_tax_zone');
        $this->addSql('ALTER TABLE external_image_error ALTER first_seen TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN external_image_error.first_seen IS NULL');
        $this->addSql('CREATE INDEX idx_external_image_error_last_seen ON external_image_error (last_seen)');
        $this->addSql('ALTER INDEX uniq_225f75aff47645ae RENAME TO uniq_external_image_error_url');
    }
}
