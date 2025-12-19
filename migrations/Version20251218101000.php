<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251218101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ shop_id sur customer_order_item pour lier les conversations aux boutiques';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order_item ADD shop_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order_item DROP COLUMN shop_id');
    }
}
