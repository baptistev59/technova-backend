<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow audit_log.owner to be nulled when the owning user is deleted.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F57E3C61F9');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F57E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F57E3C61F9');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F57E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
