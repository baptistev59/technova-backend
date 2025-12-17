<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Historique des statuts de commandes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_order_status_history (id SERIAL NOT NULL, order_entity_id INT NOT NULL, from_status VARCHAR(20) NOT NULL, to_status VARCHAR(20) NOT NULL, transition VARCHAR(50) NOT NULL, changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, triggered_by VARCHAR(120) DEFAULT NULL, payload JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_order_history_order ON customer_order_status_history (order_entity_id)');
        $this->addSql('ALTER TABLE customer_order_status_history ADD CONSTRAINT FK_order_history_order FOREIGN KEY (order_entity_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE customer_order_status_history');
    }
}
