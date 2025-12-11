<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251209103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce global attribute definitions, values and product attribute selections';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE attribute_definition (id SERIAL NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, input_type VARCHAR(40) DEFAULT \'select\' NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_attribute_definition_slug ON attribute_definition (slug)');

        $this->addSql('CREATE TABLE attribute_value_definition (id SERIAL NOT NULL, attribute_id INT NOT NULL, label VARCHAR(120) NOT NULL, value VARCHAR(120) NOT NULL, position SMALLINT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_attribute_value_definition_attribute_id ON attribute_value_definition (attribute_id)');
        $this->addSql('ALTER TABLE attribute_value_definition ADD CONSTRAINT FK_attribute_value_definition_attribute FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE product_attribute_selection (id SERIAL NOT NULL, product_id INT NOT NULL, attribute_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_product_attribute_selection_product ON product_attribute_selection (product_id)');
        $this->addSql('CREATE INDEX IDX_product_attribute_selection_attribute ON product_attribute_selection (attribute_id)');
        $this->addSql('ALTER TABLE product_attribute_selection ADD CONSTRAINT FK_product_attribute_selection_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_attribute_selection ADD CONSTRAINT FK_product_attribute_selection_attribute FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE product_attribute_selection_value (selection_id INT NOT NULL, value_id INT NOT NULL, PRIMARY KEY(selection_id, value_id))');
        $this->addSql('CREATE INDEX IDX_product_attribute_selection_value_value ON product_attribute_selection_value (value_id)');
        $this->addSql('ALTER TABLE product_attribute_selection_value ADD CONSTRAINT FK_product_attribute_selection_value_selection FOREIGN KEY (selection_id) REFERENCES product_attribute_selection (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_attribute_selection_value ADD CONSTRAINT FK_product_attribute_selection_value_value FOREIGN KEY (value_id) REFERENCES attribute_value_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_attribute_selection_value DROP CONSTRAINT FK_product_attribute_selection_value_selection');
        $this->addSql('ALTER TABLE product_attribute_selection_value DROP CONSTRAINT FK_product_attribute_selection_value_value');
        $this->addSql('ALTER TABLE attribute_value_definition DROP CONSTRAINT FK_attribute_value_definition_attribute');
        $this->addSql('ALTER TABLE product_attribute_selection DROP CONSTRAINT FK_product_attribute_selection_product');
        $this->addSql('ALTER TABLE product_attribute_selection DROP CONSTRAINT FK_product_attribute_selection_attribute');

        $this->addSql('DROP TABLE product_attribute_selection_value');
        $this->addSql('DROP TABLE product_attribute_selection');
        $this->addSql('DROP TABLE attribute_value_definition');
        $this->addSql('DROP TABLE attribute_definition');
    }
}
