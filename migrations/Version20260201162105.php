<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260201162105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_tax_zone (id SERIAL NOT NULL, product_id INT NOT NULL, tax_zone_id INT NOT NULL, tax_class VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) NOT NULL, updated_at TIMESTAMP(0), PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PRODUCT_TAX_ZONE_PRODUCT ON product_tax_zone (product_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_TAX_ZONE_TAX_ZONE ON product_tax_zone (tax_zone_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_TAX_ZONE ON product_tax_zone (product_id, tax_zone_id)');
        $this->addSql('ALTER TABLE product_tax_zone ADD CONSTRAINT fk_product_tax_zone_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_tax_zone ADD CONSTRAINT fk_product_tax_zone_tax_zone FOREIGN KEY (tax_zone_id) REFERENCES tax_zone (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE IF EXISTS product_vat_rate');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE product_tax_zone');
        $this->addSql('CREATE TABLE product_vat_rate (id SERIAL NOT NULL, product_id INT NOT NULL, country_code VARCHAR(2) NOT NULL, tax_class VARCHAR(50) NOT NULL, rate NUMERIC(5, 2), active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_product_vat_rate_product ON product_vat_rate (product_id)');

    }
}
