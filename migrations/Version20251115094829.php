<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251115094829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vendor ADD address_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD business_id_type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD website VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE vendor ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('COMMENT ON COLUMN vendor.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN vendor.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vendor ADD CONSTRAINT FK_F52233F6F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F52233F6F5B7AF75 ON vendor (address_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE vendor DROP CONSTRAINT FK_F52233F6F5B7AF75');
        $this->addSql('DROP INDEX UNIQ_F52233F6F5B7AF75');
        $this->addSql('ALTER TABLE vendor DROP address_id');
        $this->addSql('ALTER TABLE vendor DROP business_id_type');
        $this->addSql('ALTER TABLE vendor DROP email');
        $this->addSql('ALTER TABLE vendor DROP website');
        $this->addSql('ALTER TABLE vendor DROP created_at');
        $this->addSql('ALTER TABLE vendor DROP updated_at');
    }
}
