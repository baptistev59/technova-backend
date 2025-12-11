<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251208174500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ keywords sur la table product pour gérer les mots clés vendeurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD keywords VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP keywords');
    }
}
