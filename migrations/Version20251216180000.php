<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table order_document pour stocker les PDF clients.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_document (id SERIAL NOT NULL, order_entity_id INT NOT NULL, type VARCHAR(30) NOT NULL, path VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_order_document_order ON order_document (order_entity_id)');
        $this->addSql('ALTER TABLE order_document ADD CONSTRAINT FK_order_document_order FOREIGN KEY (order_entity_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE order_document');
    }
}
