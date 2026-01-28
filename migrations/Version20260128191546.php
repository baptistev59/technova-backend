<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128191546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order ALTER items_net_total DROP DEFAULT');
        $this->addSql('ALTER TABLE customer_order ALTER items_vat_total DROP DEFAULT');
        $this->addSql('ALTER TABLE customer_order ALTER items_gross_total DROP DEFAULT');
        $this->addSql('ALTER TABLE product ADD tax_class VARCHAR(32) DEFAULT \'STANDARD\' NOT NULL');
        // sequence/identity handling is managed elsewhere; skip sequence creation to avoid conflicts
        $this->addSql('DROP INDEX uniq_vat_default_global_country');
        $this->addSql('DROP INDEX uniq_vat_default_shop_country');
        $this->addSql('DROP INDEX uniq_vat_global_country_code');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE UNIQUE INDEX uniq_vat_default_global_country ON vat_rate (country_code) WHERE ((shop_id IS NULL) AND (is_default = true))');
        $this->addSql('CREATE UNIQUE INDEX uniq_vat_default_shop_country ON vat_rate (shop_id, country_code) WHERE (is_default = true)');
        $this->addSql('CREATE UNIQUE INDEX uniq_vat_global_country_code ON vat_rate (country_code, code) WHERE (shop_id IS NULL)');
        $this->addSql('ALTER TABLE product DROP tax_class');
        $this->addSql('ALTER TABLE customer_order ALTER items_net_total SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE customer_order ALTER items_vat_total SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE customer_order ALTER items_gross_total SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE reset_password_request ALTER id DROP DEFAULT');
    }
}
