<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add return requests and review reports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE return_request (id SERIAL NOT NULL, order_id INT NOT NULL, requester_id INT NOT NULL, reason VARCHAR(255) NOT NULL, details TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_return_request_order ON return_request (order_id)');
        $this->addSql('CREATE INDEX idx_return_request_requester ON return_request (requester_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_return_request_order_requester ON return_request (order_id, requester_id)');
        $this->addSql('COMMENT ON COLUMN return_request.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN return_request.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE product_review_report (id SERIAL NOT NULL, review_id INT NOT NULL, reporter_id INT NOT NULL, reason VARCHAR(255) NOT NULL, details TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_review_report_review ON product_review_report (review_id)');
        $this->addSql('CREATE INDEX idx_review_report_reporter ON product_review_report (reporter_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_review_report_review_reporter ON product_review_report (review_id, reporter_id)');
        $this->addSql('COMMENT ON COLUMN product_review_report.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_review_report.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_RETURN_REQUEST_ORDER FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE return_request ADD CONSTRAINT FK_RETURN_REQUEST_REQUESTER FOREIGN KEY (requester_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE product_review_report ADD CONSTRAINT FK_REVIEW_REPORT_REVIEW FOREIGN KEY (review_id) REFERENCES product_review (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_review_report ADD CONSTRAINT FK_REVIEW_REPORT_REPORTER FOREIGN KEY (reporter_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_review_report DROP CONSTRAINT FK_REVIEW_REPORT_REVIEW');
        $this->addSql('ALTER TABLE product_review_report DROP CONSTRAINT FK_REVIEW_REPORT_REPORTER');
        $this->addSql('ALTER TABLE return_request DROP CONSTRAINT FK_RETURN_REQUEST_ORDER');
        $this->addSql('ALTER TABLE return_request DROP CONSTRAINT FK_RETURN_REQUEST_REQUESTER');

        $this->addSql('DROP TABLE product_review_report');
        $this->addSql('DROP TABLE return_request');
    }
}
