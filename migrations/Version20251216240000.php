<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme les colonnes order_entity_id des documents et historiques en order_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_document DROP CONSTRAINT IF EXISTS FK_order_document_order');
        $this->addSql('DROP INDEX IF EXISTS IDX_order_document_order');
        $this->addSql('ALTER TABLE customer_order_status_history DROP CONSTRAINT IF EXISTS FK_order_history_order');
        $this->addSql('DROP INDEX IF EXISTS IDX_order_history_order');

        $this->addSql('ALTER TABLE order_document RENAME COLUMN order_entity_id TO order_id');
        $this->addSql('ALTER TABLE customer_order_status_history RENAME COLUMN order_entity_id TO order_id');

        $this->addSql('CREATE INDEX IDX_order_document_order ON order_document (order_id)');
        $this->addSql('CREATE INDEX IDX_order_history_order ON customer_order_status_history (order_id)');
        $this->addSql('ALTER TABLE order_document ADD CONSTRAINT FK_order_document_order FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE customer_order_status_history ADD CONSTRAINT FK_order_history_order FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_document DROP CONSTRAINT IF EXISTS FK_order_document_order');
        $this->addSql('DROP INDEX IF EXISTS IDX_order_document_order');
        $this->addSql('ALTER TABLE customer_order_status_history DROP CONSTRAINT IF EXISTS FK_order_history_order');
        $this->addSql('DROP INDEX IF EXISTS IDX_order_history_order');

        $this->addSql('ALTER TABLE order_document RENAME COLUMN order_id TO order_entity_id');
        $this->addSql('ALTER TABLE customer_order_status_history RENAME COLUMN order_id TO order_entity_id');

        $this->addSql('CREATE INDEX IDX_order_document_order ON order_document (order_entity_id)');
        $this->addSql('CREATE INDEX IDX_order_history_order ON customer_order_status_history (order_entity_id)');
        $this->addSql('ALTER TABLE order_document ADD CONSTRAINT FK_order_document_order FOREIGN KEY (order_entity_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE customer_order_status_history ADD CONSTRAINT FK_order_history_order FOREIGN KEY (order_entity_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
