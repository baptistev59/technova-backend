<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260102123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add low stock threshold to product and product_variant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD low_stock_threshold INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product_variant ADD low_stock_threshold INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP low_stock_threshold');
        $this->addSql('ALTER TABLE product_variant DROP low_stock_threshold');
    }
}
