<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refund tracking on customer orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order ADD refund_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order ADD refunded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN customer_order.refunded_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order DROP refund_id');
        $this->addSql('ALTER TABLE customer_order DROP refunded_at');
    }
}
