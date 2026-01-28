<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create external_image_error table for image proxy error logging.';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL-friendly table creation
        $this->addSql(<<<'SQL'
    CREATE TABLE external_image_error (
      id SERIAL PRIMARY KEY,
      url VARCHAR(2048) NOT NULL,
      status_code INT NOT NULL,
      occurrences INT NOT NULL,
      first_seen TIMESTAMP NOT NULL,
      last_seen TIMESTAMP DEFAULT NULL,
      CONSTRAINT uniq_external_image_error_url UNIQUE (url)
    );
    SQL
        );

        $this->addSql('CREATE INDEX idx_external_image_error_last_seen ON external_image_error (last_seen)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_external_image_error_last_seen');
        $this->addSql('DROP TABLE external_image_error');
    }
}
