<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tax_zone relation to product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD tax_zone_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PRODUCT_TAX_ZONE ON product (tax_zone_id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_PRODUCT_TAX_ZONE FOREIGN KEY (tax_zone_id) REFERENCES tax_zone (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT IF EXISTS FK_PRODUCT_TAX_ZONE');
        $this->addSql('DROP INDEX IF EXISTS IDX_PRODUCT_TAX_ZONE');
        $this->addSql('ALTER TABLE product DROP tax_zone_id');
    }
}
