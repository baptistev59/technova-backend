<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251206180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des colonnes payment_session_id et payment_intent_id sur customer_order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order ADD payment_session_id VARCHAR(120) DEFAULT NULL, ADD payment_intent_id VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_order DROP payment_session_id, DROP payment_intent_id');
    }
}
