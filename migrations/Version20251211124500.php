<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associe chaque attribut global à une boutique (shop_id sur attribute_definition).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition ADD shop_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attribute_definition ADD CONSTRAINT FK_A1A4DFE84C569ED FOREIGN KEY (shop_id) REFERENCES shop (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A1A4DFE84C569ED ON attribute_definition (shop_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_definition DROP CONSTRAINT FK_A1A4DFE84C569ED');
        $this->addSql('DROP INDEX IDX_A1A4DFE84C569ED');
        $this->addSql('ALTER TABLE attribute_definition DROP shop_id');
    }
}

