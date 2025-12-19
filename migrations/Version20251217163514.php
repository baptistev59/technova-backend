<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251217163514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration neutralisée (refactor multi-shop, shop_id déjà supprimé)';
    }

    public function up(Schema $schema): void
    {
        // Migration volontairement vide
    }

    public function down(Schema $schema): void
    {
        // No-op
    }
}
