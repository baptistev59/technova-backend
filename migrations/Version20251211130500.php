<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211130500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nettoyage de l’index unique global sur attribute_definition.slug (désormais unique par boutique).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_attribute_definition_slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_attribute_definition_slug ON attribute_definition (slug)');
    }
}

