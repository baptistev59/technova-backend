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
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_PRODUCT_TAX_ZONE FOREIGN KEY (tax_zone_id) REFERENCES tax_zone (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP CONSTRAINT IF EXISTS FK_PRODUCT_TAX_ZONE');
    }
}
