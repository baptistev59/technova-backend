<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251210110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_bundle_item table for grouped products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_bundle_item (id SERIAL NOT NULL, bundle_id INT NOT NULL, component_id INT NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, is_required BOOLEAN DEFAULT FALSE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_product_bundle_item_bundle ON product_bundle_item (bundle_id)');
        $this->addSql('CREATE INDEX IDX_product_bundle_item_component ON product_bundle_item (component_id)');
        $this->addSql('ALTER TABLE product_bundle_item ADD CONSTRAINT FK_product_bundle_item_bundle FOREIGN KEY (bundle_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_bundle_item ADD CONSTRAINT FK_product_bundle_item_component FOREIGN KEY (component_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_bundle_item DROP CONSTRAINT FK_product_bundle_item_bundle');
        $this->addSql('ALTER TABLE product_bundle_item DROP CONSTRAINT FK_product_bundle_item_component');
        $this->addSql('DROP TABLE product_bundle_item');
    }
}
