<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ variant_id sur customer_order_item pour gérer la décrémentation de stock.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order_item ADD variant_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order_item DROP variant_id');
    }
}

