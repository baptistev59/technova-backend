<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add country table with name/flag mapping for VAT country list.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE country (code VARCHAR(2) NOT NULL, name VARCHAR(64) NOT NULL, flag VARCHAR(8) NOT NULL, PRIMARY KEY(code))');

        $this->addSql("INSERT INTO country (code, name, flag) VALUES
            ('FR', 'France', '🇫🇷'),
            ('BE', 'Belgique', '🇧🇪'),
            ('DE', 'Allemagne', '🇩🇪'),
            ('IT', 'Italie', '🇮🇹'),
            ('ES', 'Espagne', '🇪🇸'),
            ('NL', 'Pays-Bas', '🇳🇱'),
            ('AT', 'Autriche', '🇦🇹'),
            ('LU', 'Luxembourg', '🇱🇺'),
            ('IE', 'Irlande', '🇮🇪'),
            ('CY', 'Chypre', '🇨🇾'),
            ('EE', 'Estonie', '🇪🇪'),
            ('FI', 'Finlande', '🇫🇮'),
            ('GR', 'Grèce', '🇬🇷'),
            ('LV', 'Lettonie', '🇱🇻'),
            ('LT', 'Lituanie', '🇱🇹'),
            ('MT', 'Malte', '🇲🇹'),
            ('PL', 'Pologne', '🇵🇱'),
            ('PT', 'Portugal', '🇵🇹'),
            ('SK', 'Slovaquie', '🇸🇰'),
            ('SI', 'Slovénie', '🇸🇮'),
            ('CZ', 'République Tchèque', '🇨🇿'),
            ('HU', 'Hongrie', '🇭🇺'),
            ('RO', 'Roumanie', '🇷🇴'),
            ('BG', 'Bulgarie', '🇧🇬'),
            ('HR', 'Croatie', '🇭🇷'),
            ('CH', 'Suisse', '🇨🇭'),
            ('UK', 'Royaume-Uni', '🇬🇧'),
            ('SE', 'Suède', '🇸🇪'),
            ('DK', 'Danemark', '🇩🇰'),
            ('NO', 'Norvège', '🇳🇴')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE country');
    }
}
