<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index for customer_order status and created_at.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_customer_order_status_created_at ON customer_order (status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_customer_order_status_created_at');
    }
}
