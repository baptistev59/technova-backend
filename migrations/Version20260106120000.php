<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on product_review (author_id, product_id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_review_author_product ON product_review (author_id, product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_review_author_product');
    }
}
