<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fulfillment status to customer order items for per-shop tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order_item ADD fulfillment_status VARCHAR(16) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE customer_order_item ADD fulfilled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order_item DROP fulfillment_status');
        $this->addSql('ALTER TABLE customer_order_item DROP fulfilled_at');
    }
}
