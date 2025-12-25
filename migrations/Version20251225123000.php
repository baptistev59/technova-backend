<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251225123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add 2FA fields to user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD email_auth_code VARCHAR(6) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD email_auth_code_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD totp_secret VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD trusted_token_version INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP email_auth_code');
        $this->addSql('ALTER TABLE "user" DROP email_auth_code_expires_at');
        $this->addSql('ALTER TABLE "user" DROP totp_secret');
        $this->addSql('ALTER TABLE "user" DROP trusted_token_version');
    }
}
