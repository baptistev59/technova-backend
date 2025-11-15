<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251115073718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE vendor (id SERIAL NOT NULL, company_name VARCHAR(255) NOT NULL, business_id VARCHAR(255) DEFAULT NULL, phone VARCHAR(25) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE "user" DROP vendor');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE vendor');
        $this->addSql('ALTER TABLE "user" ADD vendor VARCHAR(255) NOT NULL');
    }
}
