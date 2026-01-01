<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add advanced shipping configuration and weight-based delivery fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product ADD weight NUMERIC(10, 3) DEFAULT '0.000' NOT NULL");
        $this->addSql('ALTER TABLE product_variant ADD weight NUMERIC(10, 3) DEFAULT NULL');

        $this->addSql("ALTER TABLE customer_order ADD items_total NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql("ALTER TABLE customer_order ADD shipping_total NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql('ALTER TABLE customer_order ADD shipping_lines JSON DEFAULT NULL');
        $this->addSql('UPDATE customer_order SET items_total = total_amount WHERE items_total = 0.00');

        $this->addSql('CREATE TABLE shipping_zone (id SERIAL NOT NULL, shop_id INT NOT NULL, name VARCHAR(120) NOT NULL, countries JSON NOT NULL, postal_codes JSON DEFAULT NULL, is_active BOOLEAN DEFAULT TRUE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_shipping_zone_shop ON shipping_zone (shop_id)');

        $this->addSql('CREATE TABLE shipping_method (id SERIAL NOT NULL, shop_id INT NOT NULL, zone_id INT NOT NULL, name VARCHAR(120) NOT NULL, carrier_name VARCHAR(120) DEFAULT NULL, min_days INT DEFAULT NULL, max_days INT DEFAULT NULL, is_active BOOLEAN DEFAULT TRUE NOT NULL, sort_order INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_shipping_method_shop ON shipping_method (shop_id)');
        $this->addSql('CREATE INDEX idx_shipping_method_zone ON shipping_method (zone_id)');

        $this->addSql('CREATE TABLE shipping_rate (id SERIAL NOT NULL, method_id INT NOT NULL, min_weight NUMERIC(10, 3) NOT NULL, max_weight NUMERIC(10, 3) DEFAULT NULL, price NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_shipping_rate_method ON shipping_rate (method_id)');

        $this->addSql('ALTER TABLE shipping_zone ADD CONSTRAINT FK_SHIPPING_ZONE_SHOP FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT FK_SHIPPING_METHOD_SHOP FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT FK_SHIPPING_METHOD_ZONE FOREIGN KEY (zone_id) REFERENCES shipping_zone (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shipping_rate ADD CONSTRAINT FK_SHIPPING_RATE_METHOD FOREIGN KEY (method_id) REFERENCES shipping_method (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shipping_rate DROP CONSTRAINT FK_SHIPPING_RATE_METHOD');
        $this->addSql('ALTER TABLE shipping_method DROP CONSTRAINT FK_SHIPPING_METHOD_ZONE');
        $this->addSql('ALTER TABLE shipping_method DROP CONSTRAINT FK_SHIPPING_METHOD_SHOP');
        $this->addSql('ALTER TABLE shipping_zone DROP CONSTRAINT FK_SHIPPING_ZONE_SHOP');

        $this->addSql('DROP TABLE shipping_rate');
        $this->addSql('DROP TABLE shipping_method');
        $this->addSql('DROP TABLE shipping_zone');

        $this->addSql('ALTER TABLE customer_order DROP shipping_lines');
        $this->addSql('ALTER TABLE customer_order DROP shipping_total');
        $this->addSql('ALTER TABLE customer_order DROP items_total');
        $this->addSql('ALTER TABLE product_variant DROP weight');
        $this->addSql('ALTER TABLE product DROP weight');
    }
}
