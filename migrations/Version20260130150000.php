<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tax_zone table with preset zones (EU, UK/IE, CH/LI)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tax_zone (id SERIAL PRIMARY KEY, shop_id INT, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT, country_codes JSON NOT NULL, tax_class VARCHAR(32) NOT NULL DEFAULT \'STANDARD\', rate NUMERIC(5, 2) NOT NULL DEFAULT 0, is_preset BOOLEAN NOT NULL DEFAULT false, sort_order INT NOT NULL DEFAULT 999, active BOOLEAN NOT NULL DEFAULT true, created_at TIMESTAMP(0) NOT NULL, updated_at TIMESTAMP(0), FOREIGN KEY (shop_id) REFERENCES shop(id) ON DELETE SET NULL)');
        $this->addSql('CREATE INDEX idx_tax_zone_shop ON tax_zone (shop_id)');
        $this->addSql('CREATE INDEX idx_tax_zone_is_preset ON tax_zone (is_preset)');

        // Insert preset zones
        $euCountries = json_encode(['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE']);
        $ukIe = json_encode(['GB', 'IE']);
        $chLi = json_encode(['CH', 'LI']);

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $this->addSql("INSERT INTO tax_zone (code, name, description, country_codes, tax_class, rate, is_preset, sort_order, active, created_at, updated_at) VALUES
            ('EU_STANDARD', 'Union Européenne — Standard', 'TVA harmonisée 19-27% pour 27 pays de l''UE', '{$euCountries}', 'STANDARD', 20.00, true, 1, true, '{$now}', '{$now}'),
            ('EU_REDUCED', 'Union Européenne — Réduit', 'TVA réduite 5-13% pour biens et services spécifiques', '{$euCountries}', 'REDUCED', 10.00, true, 2, true, '{$now}', '{$now}'),
            ('EU_ZERO', 'Union Européenne — Zéro', 'TVA 0% pour services exportés et transactions B2B intra-UE', '{$euCountries}', 'ZERO', 0.00, true, 3, false, '{$now}', '{$now}'),
            ('UK_IRELAND', 'Royaume-Uni & Irlande', 'TVA UK 20%, Irlande 23% (post-Brexit)', '{$ukIe}', 'STANDARD', 20.00, true, 4, true, '{$now}', '{$now}'),
            ('SWISS_LIECHTENSTEIN', 'Suisse & Liechtenstein', 'TVA 7.7% (zone AELE hors UE)', '{$chLi}', 'STANDARD', 7.70, true, 5, true, '{$now}', '{$now}')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tax_zone');
    }
}
