<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251218100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Étend la longueur de customer_order.reference à 80 caractères';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order ALTER COLUMN reference TYPE VARCHAR(80)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order ALTER COLUMN reference TYPE VARCHAR(40)');
    }
}
