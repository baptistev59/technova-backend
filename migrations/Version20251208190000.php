<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251208190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ promo_price sur product pour gérer un prix promotionnel HT.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD promo_price NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP promo_price');
    }
}
