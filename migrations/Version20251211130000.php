<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index unique composite (slug, shop_id) pour attribute_definition et retrait de l’unicité globale.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_attribute_definition_slug');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ATTRIBUTE_DEFINITION_SHOP_SLUG ON attribute_definition (shop_id, slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_ATTRIBUTE_DEFINITION_SHOP_SLUG');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_attribute_definition_slug ON attribute_definition (slug)');
    }
}
