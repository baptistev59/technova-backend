<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205042506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_vat_rate (id SERIAL NOT NULL, product_id INT NOT NULL, vat_rate_id INT NOT NULL, country_code VARCHAR(2) NOT NULL, active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3D349D5043897540 ON product_vat_rate (vat_rate_id)');
        $this->addSql('CREATE INDEX idx_product_vat_rate_product ON product_vat_rate (product_id)');
        $this->addSql('CREATE INDEX idx_product_vat_rate_country ON product_vat_rate (country_code)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_country ON product_vat_rate (product_id, country_code)');
        $this->addSql('COMMENT ON COLUMN product_vat_rate.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tax_zone (id SERIAL NOT NULL, shop_id INT DEFAULT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, country_codes JSON NOT NULL, tax_class VARCHAR(32) NOT NULL, rate NUMERIC(5, 2) NOT NULL, is_preset BOOLEAN NOT NULL, sort_order INT NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BC64CFFF4D16C4DD ON tax_zone (shop_id)');
        $this->addSql('COMMENT ON COLUMN tax_zone.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE product_vat_rate ADD CONSTRAINT FK_3D349D504584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_vat_rate ADD CONSTRAINT FK_3D349D5043897540 FOREIGN KEY (vat_rate_id) REFERENCES vat_rate (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tax_zone ADD CONSTRAINT FK_BC64CFFF4D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_product_tax_zone_countries');
        $this->addSql('ALTER TABLE product_tax_zone ALTER country_codes DROP DEFAULT');
        $this->addSql('ALTER TABLE product_tax_zone ALTER country_codes SET NOT NULL');
        $this->addSql('ALTER INDEX idx_product_tax_zone_product RENAME TO IDX_77D4A5684584665A');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE product_vat_rate DROP CONSTRAINT FK_3D349D504584665A');
        $this->addSql('ALTER TABLE product_vat_rate DROP CONSTRAINT FK_3D349D5043897540');
        $this->addSql('ALTER TABLE tax_zone DROP CONSTRAINT FK_BC64CFFF4D16C4DD');
        $this->addSql('DROP TABLE product_vat_rate');
        $this->addSql('DROP TABLE tax_zone');
        $this->addSql('ALTER TABLE reset_password_request ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE product_tax_zone ALTER country_codes SET DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE product_tax_zone ALTER country_codes DROP NOT NULL');
        $this->addSql('CREATE INDEX idx_product_tax_zone_countries ON product_tax_zone (country_codes)');
        $this->addSql('ALTER INDEX idx_77d4a5684584665a RENAME TO idx_product_tax_zone_product');
    }
}
