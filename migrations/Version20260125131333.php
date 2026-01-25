<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125131333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Wishlist table for user favorites.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wishlist (id SERIAL NOT NULL, user_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9CE12A31A76ED395 ON wishlist (user_id)');
        $this->addSql('CREATE INDEX IDX_9CE12A314584665A ON wishlist (product_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_user_product ON wishlist (user_id, product_id)');
        $this->addSql('COMMENT ON COLUMN wishlist.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A31A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A314584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wishlist');
    }
}
