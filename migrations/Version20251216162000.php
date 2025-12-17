<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la table media pour stocker les uploads liés aux vendeurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE media (id SERIAL NOT NULL, vendor_id INT NOT NULL, profile VARCHAR(50) NOT NULL, path VARCHAR(255) NOT NULL, width INT NOT NULL, height INT NOT NULL, mime_type VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_media_vendor ON media (vendor_id)');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_media_vendor FOREIGN KEY (vendor_id) REFERENCES vendor (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE media');
    }
}
