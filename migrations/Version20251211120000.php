<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ bundle_discount_percent sur product pour gérer la remise globale des packs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD bundle_discount_percent NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP bundle_discount_percent');
    }
}

