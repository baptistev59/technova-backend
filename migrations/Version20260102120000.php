<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260102120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields to user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD is_email_verified BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD email_verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD email_verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX user_email_verification_token_idx ON "user" (email_verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_email_verification_token_idx');
        $this->addSql('ALTER TABLE "user" DROP is_email_verified');
        $this->addSql('ALTER TABLE "user" DROP email_verification_token');
        $this->addSql('ALTER TABLE "user" DROP email_verification_expires_at');
    }
}
