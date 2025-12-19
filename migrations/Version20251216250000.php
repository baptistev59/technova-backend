<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les conversations et messages pour la messagerie interne.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation (id SERIAL NOT NULL, order_id INT NOT NULL, shop_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_conversation_order ON conversation (order_id)');
        $this->addSql('CREATE INDEX IDX_conversation_shop ON conversation (shop_id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conversation_order FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conversation_shop FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE message (id SERIAL NOT NULL, conversation_id INT NOT NULL, author_id INT NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_message_conversation ON message (conversation_id)');
        $this->addSql('CREATE INDEX IDX_message_author ON message (author_id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_message_conversation FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_message_author FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP CONSTRAINT IF EXISTS FK_message_author');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT IF EXISTS FK_message_conversation');
        $this->addSql('DROP TABLE message');

        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT IF EXISTS FK_conversation_shop');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT IF EXISTS FK_conversation_order');
        $this->addSql('DROP TABLE conversation');
    }
}
