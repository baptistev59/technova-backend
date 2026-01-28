<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128174732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE vat_rate (id SERIAL NOT NULL, shop_id INT DEFAULT NULL, country_code VARCHAR(2) NOT NULL, code VARCHAR(32) NOT NULL, label VARCHAR(64) DEFAULT NULL, type VARCHAR(32) DEFAULT NULL, rate NUMERIC(5, 2) NOT NULL, is_default BOOLEAN NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F684F7C74D16C4DD ON vat_rate (shop_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_vat_shop_country_code ON vat_rate (shop_id, country_code, code)');
        // PostgreSQL: ensure a single global (shop_id IS NULL) per (country_code, code)
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vat_global_country_code ON vat_rate (country_code, code) WHERE shop_id IS NULL');
        // Ensure only one default per (shop_id, country_code) when is_default = true
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vat_default_shop_country ON vat_rate (shop_id, country_code) WHERE is_default = true');
        // And for global defaults (shop_id IS NULL)
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_vat_default_global_country ON vat_rate (country_code) WHERE shop_id IS NULL AND is_default = true');
        $this->addSql('COMMENT ON COLUMN vat_rate.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vat_rate ADD CONSTRAINT FK_F684F7C74D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX uniq_attribute_definition_shop_slug');
        $this->addSql('ALTER INDEX idx_a1a4dfe84c569ed RENAME TO IDX_6C5628BD4D16C4DD');
        $this->addSql('ALTER TABLE attribute_value_definition DROP CONSTRAINT fk_attribute_value_definition_attribute');
        $this->addSql('ALTER TABLE attribute_value_definition ADD CONSTRAINT FK_B92AE99EB6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_attribute_value_definition_attribute_id RENAME TO IDX_B92AE99EB6E62EFA');
        // If a unique constraint exists on brand.name drop it (Postgres requires dropping constraint)
        $this->addSql('ALTER TABLE brand DROP CONSTRAINT IF EXISTS uniq_brand_name');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BRAND_SLUG ON brand (slug)');
        $this->addSql('DROP INDEX idx_conversation_shop');
        $this->addSql('DROP INDEX idx_conversation_shop_id');
        $this->addSql('ALTER TABLE conversation ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE conversation ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN conversation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN conversation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_8A8E26E94D16C4DD ON conversation (shop_id)');
        $this->addSql('ALTER INDEX uniq_conversation_order RENAME TO UNIQ_8A8E26E98D9F6D38');
        $this->addSql('ALTER TABLE customer_order DROP CONSTRAINT fk_order_owner');
        $this->addSql('ALTER TABLE customer_order ALTER items_total DROP DEFAULT');
        // Add aggregated VAT totals: items net / vat / gross
        $this->addSql('ALTER TABLE customer_order ADD items_net_total NUMERIC(10,2) NOT NULL DEFAULT 0.00');
        $this->addSql('ALTER TABLE customer_order ADD items_vat_total NUMERIC(10,2) NOT NULL DEFAULT 0.00');
        $this->addSql('ALTER TABLE customer_order ADD items_gross_total NUMERIC(10,2) NOT NULL DEFAULT 0.00');
        $this->addSql('ALTER TABLE customer_order ALTER shipping_total DROP DEFAULT');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A37E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_customer_order_reference RENAME TO UNIQ_3B1CE6A3AEA34913');
        $this->addSql('ALTER TABLE customer_order_item DROP CONSTRAINT fk_order_item_order');
        $this->addSql('ALTER TABLE customer_order_item ADD applied_vat_percent NUMERIC(5, 2) NOT NULL');
        $this->addSql('ALTER TABLE customer_order_item ADD applied_vat_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE customer_order_item ADD applied_net_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE customer_order_item ADD applied_gross_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE customer_order_item ADD vat_country_code VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order_item ADD applied_vat_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order_item ALTER fulfillment_status DROP DEFAULT');
        $this->addSql('ALTER TABLE customer_order_item ALTER fulfilled_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN customer_order_item.fulfilled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE customer_order_item ADD CONSTRAINT FK_AF231B8BA15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_order_item_order RENAME TO IDX_AF231B8BA15A2E17');
        $this->addSql('ALTER TABLE customer_order_status_history DROP CONSTRAINT fk_order_history_order');
        $this->addSql('DROP INDEX idx_order_history_order');
        $this->addSql('ALTER TABLE customer_order_status_history ALTER changed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE customer_order_status_history ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE customer_order_status_history ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE customer_order_status_history RENAME COLUMN order_id TO order_entity_id');
        $this->addSql('COMMENT ON COLUMN customer_order_status_history.changed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN customer_order_status_history.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN customer_order_status_history.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE customer_order_status_history ADD CONSTRAINT FK_9846584B3DA206A5 FOREIGN KEY (order_entity_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_9846584B3DA206A5 ON customer_order_status_history (order_entity_id)');
        $this->addSql('ALTER TABLE media ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE media ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN media.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN media.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_media_vendor RENAME TO IDX_6A2CA10CF603EE73');
        $this->addSql('ALTER TABLE message ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE message ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN message.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN message.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_message_conversation RENAME TO IDX_B6BD307F9AC0396');
        $this->addSql('ALTER INDEX idx_message_author RENAME TO IDX_B6BD307FF675F31B');
        $this->addSql('ALTER TABLE order_document ALTER type TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE order_document ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE order_document ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN order_document.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_document.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX idx_order_document_order RENAME TO IDX_399168C78D9F6D38');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_SLUG ON product (slug)');
        $this->addSql('ALTER TABLE product_attribute DROP CONSTRAINT FK_94DA59764584665A');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT FK_94DA59764584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_attribute_selection DROP CONSTRAINT fk_product_attribute_selection_attribute');
        $this->addSql('ALTER TABLE product_attribute_selection DROP CONSTRAINT fk_product_attribute_selection_product');
        $this->addSql('ALTER TABLE product_attribute_selection ADD CONSTRAINT FK_72C597C34584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_attribute_selection ADD CONSTRAINT FK_72C597C3B6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_product_attribute_selection_product RENAME TO IDX_72C597C34584665A');
        $this->addSql('ALTER INDEX idx_product_attribute_selection_attribute RENAME TO IDX_72C597C3B6E62EFA');
        $this->addSql('ALTER INDEX idx_product_attribute_selection_value_value RENAME TO IDX_75F381B8F920BBA2');
        $this->addSql('ALTER TABLE product_attribute_value DROP CONSTRAINT FK_CCC4BE1FB6E62EFA');
        $this->addSql('ALTER TABLE product_attribute_value ADD CONSTRAINT FK_CCC4BE1FB6E62EFA FOREIGN KEY (attribute_id) REFERENCES product_attribute (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_bundle_item DROP CONSTRAINT fk_product_bundle_item_bundle');
        $this->addSql('ALTER TABLE product_bundle_item DROP CONSTRAINT fk_product_bundle_item_component');
        $this->addSql('ALTER TABLE product_bundle_item ADD CONSTRAINT FK_8F43A0C1F1FAD9D3 FOREIGN KEY (bundle_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_bundle_item ADD CONSTRAINT FK_8F43A0C1E2ABAFFF FOREIGN KEY (component_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_product_bundle_item_bundle RENAME TO IDX_8F43A0C1F1FAD9D3');
        $this->addSql('ALTER INDEX idx_product_bundle_item_component RENAME TO IDX_8F43A0C1E2ABAFFF');
        $this->addSql('DROP INDEX uniq_review_report_review_reporter');
        $this->addSql('ALTER INDEX idx_review_report_review RENAME TO IDX_94B42CB63E2E969B');
        $this->addSql('ALTER INDEX idx_review_report_reporter RENAME TO IDX_94B42CB6E1CFE6F5');
        $this->addSql('ALTER TABLE product_variant DROP CONSTRAINT FK_209AA41D4584665A');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX reset_password_selector_unique');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX reset_password_user_idx RENAME TO IDX_7CE748AA76ED395');
        $this->addSql('DROP INDEX uniq_return_request_order_requester');
        $this->addSql('ALTER INDEX idx_return_request_order RENAME TO IDX_2DBF9D408D9F6D38');
        $this->addSql('ALTER INDEX idx_return_request_requester RENAME TO IDX_2DBF9D40ED442CF4');
        $this->addSql('ALTER TABLE saved_cart DROP CONSTRAINT fk_saved_cart_owner');
        $this->addSql('ALTER TABLE saved_cart ADD CONSTRAINT FK_59C7AA27E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_saved_cart_owner RENAME TO UNIQ_59C7AA27E3C61F9');
        $this->addSql('ALTER TABLE shipping_method DROP CONSTRAINT fk_shipping_method_shop');
        $this->addSql('ALTER TABLE shipping_method DROP CONSTRAINT fk_shipping_method_zone');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT FK_7503FF2F4D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT FK_7503FF2F9F2C3FAB FOREIGN KEY (zone_id) REFERENCES shipping_zone (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_shipping_method_shop RENAME TO IDX_7503FF2F4D16C4DD');
        $this->addSql('ALTER INDEX idx_shipping_method_zone RENAME TO IDX_7503FF2F9F2C3FAB');
        $this->addSql('ALTER TABLE shipping_rate DROP CONSTRAINT fk_shipping_rate_method');
        $this->addSql('ALTER TABLE shipping_rate ADD CONSTRAINT FK_4E50A93B19883967 FOREIGN KEY (method_id) REFERENCES shipping_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_shipping_rate_method RENAME TO IDX_4E50A93B19883967');
        $this->addSql('ALTER TABLE shipping_zone DROP CONSTRAINT fk_shipping_zone_shop');
        $this->addSql('ALTER TABLE shipping_zone ADD CONSTRAINT FK_315756054D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_shipping_zone_shop RENAME TO IDX_315756054D16C4DD');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SHOP_SLUG ON shop (slug)');
        $this->addSql('DROP INDEX user_email_verification_token_idx');
        $this->addSql('ALTER TABLE "user" ALTER email_auth_code_expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE "user" ALTER email_verification_expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN "user".email_auth_code_expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".email_verification_expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_wishlist_user_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE vat_rate DROP CONSTRAINT FK_F684F7C74D16C4DD');
        // Drop partial/unique indexes if exist
        $this->addSql('DROP INDEX IF EXISTS uniq_vat_default_global_country');
        $this->addSql('DROP INDEX IF EXISTS uniq_vat_default_shop_country');
        $this->addSql('DROP INDEX IF EXISTS uniq_vat_global_country_code');
        $this->addSql('DROP INDEX IF EXISTS uniq_vat_shop_country_code');
        $this->addSql('DROP TABLE vat_rate');
        $this->addSql('ALTER TABLE shipping_method DROP CONSTRAINT FK_7503FF2F4D16C4DD');
        $this->addSql('ALTER TABLE shipping_method DROP CONSTRAINT FK_7503FF2F9F2C3FAB');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT fk_shipping_method_shop FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shipping_method ADD CONSTRAINT fk_shipping_method_zone FOREIGN KEY (zone_id) REFERENCES shipping_zone (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_7503ff2f4d16c4dd RENAME TO idx_shipping_method_shop');
        $this->addSql('ALTER INDEX idx_7503ff2f9f2c3fab RENAME TO idx_shipping_method_zone');
        $this->addSql('ALTER TABLE shipping_zone DROP CONSTRAINT FK_315756054D16C4DD');
        $this->addSql('ALTER TABLE shipping_zone ADD CONSTRAINT fk_shipping_zone_shop FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_315756054d16c4dd RENAME TO idx_shipping_zone_shop');
        $this->addSql('ALTER TABLE message ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE message ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN message.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN message.updated_at IS NULL');
        $this->addSql('ALTER INDEX idx_b6bd307ff675f31b RENAME TO idx_message_author');
        $this->addSql('ALTER INDEX idx_b6bd307f9ac0396 RENAME TO idx_message_conversation');
        $this->addSql('ALTER TABLE customer_order DROP CONSTRAINT FK_3B1CE6A37E3C61F9');
        // Drop aggregated VAT totals
        $this->addSql('ALTER TABLE customer_order DROP COLUMN IF EXISTS items_net_total');
        $this->addSql('ALTER TABLE customer_order DROP COLUMN IF EXISTS items_vat_total');
        $this->addSql('ALTER TABLE customer_order DROP COLUMN IF EXISTS items_gross_total');
        $this->addSql('ALTER TABLE customer_order ALTER items_total SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE customer_order ALTER shipping_total SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT fk_order_owner FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_3b1ce6a3aea34913 RENAME TO uniq_customer_order_reference');
        $this->addSql('DROP INDEX UNIQ_BRAND_SLUG');
        $this->addSql('CREATE UNIQUE INDEX uniq_brand_name ON brand (name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_return_request_order_requester ON return_request (order_id, requester_id)');
        $this->addSql('ALTER INDEX idx_2dbf9d408d9f6d38 RENAME TO idx_return_request_order');
        $this->addSql('ALTER INDEX idx_2dbf9d40ed442cf4 RENAME TO idx_return_request_requester');
        $this->addSql('DROP INDEX UNIQ_SHOP_SLUG');
        $this->addSql('CREATE UNIQUE INDEX uniq_attribute_definition_shop_slug ON attribute_definition (shop_id, slug)');
        $this->addSql('ALTER INDEX idx_6c5628bd4d16c4dd RENAME TO idx_a1a4dfe84c569ed');
        $this->addSql('ALTER TABLE attribute_value_definition DROP CONSTRAINT FK_B92AE99EB6E62EFA');
        $this->addSql('ALTER TABLE attribute_value_definition ADD CONSTRAINT fk_attribute_value_definition_attribute FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_b92ae99eb6e62efa RENAME TO idx_attribute_value_definition_attribute_id');
        $this->addSql('CREATE INDEX idx_wishlist_user_id ON wishlist (user_id)');
        $this->addSql('ALTER TABLE product_attribute_value DROP CONSTRAINT fk_ccc4be1fb6e62efa');
        $this->addSql('ALTER TABLE product_attribute_value ADD CONSTRAINT fk_ccc4be1fb6e62efa FOREIGN KEY (attribute_id) REFERENCES product_attribute (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX UNIQ_PRODUCT_SLUG');
        $this->addSql('ALTER TABLE product_attribute_selection DROP CONSTRAINT FK_72C597C34584665A');
        $this->addSql('ALTER TABLE product_attribute_selection DROP CONSTRAINT FK_72C597C3B6E62EFA');
        $this->addSql('ALTER TABLE product_attribute_selection ADD CONSTRAINT fk_product_attribute_selection_attribute FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_attribute_selection ADD CONSTRAINT fk_product_attribute_selection_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_72c597c3b6e62efa RENAME TO idx_product_attribute_selection_attribute');
        $this->addSql('ALTER INDEX idx_72c597c34584665a RENAME TO idx_product_attribute_selection_product');
        $this->addSql('ALTER TABLE order_document ALTER type TYPE VARCHAR(30)');
        $this->addSql('ALTER TABLE order_document ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE order_document ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN order_document.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN order_document.updated_at IS NULL');
        $this->addSql('ALTER INDEX idx_399168c78d9f6d38 RENAME TO idx_order_document_order');
        $this->addSql('ALTER TABLE product_attribute DROP CONSTRAINT fk_94da59764584665a');
        $this->addSql('ALTER TABLE product_attribute ADD CONSTRAINT fk_94da59764584665a FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE media ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE media ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN media.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN media.updated_at IS NULL');
        $this->addSql('ALTER INDEX idx_6a2ca10cf603ee73 RENAME TO idx_media_vendor');
        $this->addSql('ALTER TABLE customer_order_item DROP CONSTRAINT FK_AF231B8BA15A2E17');
        $this->addSql('ALTER TABLE customer_order_item DROP applied_vat_percent');
        $this->addSql('ALTER TABLE customer_order_item DROP applied_vat_amount');
        $this->addSql('ALTER TABLE customer_order_item DROP applied_net_amount');
        $this->addSql('ALTER TABLE customer_order_item DROP applied_gross_amount');
        $this->addSql('ALTER TABLE customer_order_item DROP vat_country_code');
        $this->addSql('ALTER TABLE customer_order_item DROP applied_vat_id');
        $this->addSql('ALTER TABLE customer_order_item ALTER fulfillment_status SET DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE customer_order_item ALTER fulfilled_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN customer_order_item.fulfilled_at IS NULL');
        $this->addSql('ALTER TABLE customer_order_item ADD CONSTRAINT fk_order_item_order FOREIGN KEY (customer_order_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_af231b8ba15a2e17 RENAME TO idx_order_item_order');
        $this->addSql('ALTER TABLE reset_password_request ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN reset_password_request.requested_at IS NULL');
        $this->addSql('COMMENT ON COLUMN reset_password_request.expires_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX reset_password_selector_unique ON reset_password_request (selector)');
        $this->addSql('ALTER INDEX idx_7ce748aa76ed395 RENAME TO reset_password_user_idx');
        $this->addSql('ALTER TABLE customer_order_status_history DROP CONSTRAINT FK_9846584B3DA206A5');
        $this->addSql('DROP INDEX IDX_9846584B3DA206A5');
        $this->addSql('ALTER TABLE customer_order_status_history ALTER changed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE customer_order_status_history ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE customer_order_status_history ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE customer_order_status_history RENAME COLUMN order_entity_id TO order_id');
        $this->addSql('COMMENT ON COLUMN customer_order_status_history.changed_at IS NULL');
        $this->addSql('COMMENT ON COLUMN customer_order_status_history.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN customer_order_status_history.updated_at IS NULL');
        $this->addSql('ALTER TABLE customer_order_status_history ADD CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES customer_order (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_order_history_order ON customer_order_status_history (order_id)');
        $this->addSql('ALTER TABLE "user" ALTER email_verification_expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE "user" ALTER email_auth_code_expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN "user".email_verification_expires_at IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".email_auth_code_expires_at IS NULL');
        $this->addSql('CREATE INDEX user_email_verification_token_idx ON "user" (email_verification_token)');
        $this->addSql('CREATE UNIQUE INDEX uniq_review_report_review_reporter ON product_review_report (review_id, reporter_id)');
        $this->addSql('ALTER INDEX idx_94b42cb6e1cfe6f5 RENAME TO idx_review_report_reporter');
        $this->addSql('ALTER INDEX idx_94b42cb63e2e969b RENAME TO idx_review_report_review');
        $this->addSql('ALTER TABLE shipping_rate DROP CONSTRAINT FK_4E50A93B19883967');
        $this->addSql('ALTER TABLE shipping_rate ADD CONSTRAINT fk_shipping_rate_method FOREIGN KEY (method_id) REFERENCES shipping_method (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_4e50a93b19883967 RENAME TO idx_shipping_rate_method');
        $this->addSql('ALTER INDEX idx_75f381b8f920bba2 RENAME TO idx_product_attribute_selection_value_value');
        $this->addSql('ALTER TABLE product_bundle_item DROP CONSTRAINT FK_8F43A0C1F1FAD9D3');
        $this->addSql('ALTER TABLE product_bundle_item DROP CONSTRAINT FK_8F43A0C1E2ABAFFF');
        $this->addSql('ALTER TABLE product_bundle_item ADD CONSTRAINT fk_product_bundle_item_bundle FOREIGN KEY (bundle_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_bundle_item ADD CONSTRAINT fk_product_bundle_item_component FOREIGN KEY (component_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_8f43a0c1f1fad9d3 RENAME TO idx_product_bundle_item_bundle');
        $this->addSql('ALTER INDEX idx_8f43a0c1e2abafff RENAME TO idx_product_bundle_item_component');
        $this->addSql('ALTER TABLE product_variant DROP CONSTRAINT fk_209aa41d4584665a');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT fk_209aa41d4584665a FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE saved_cart DROP CONSTRAINT FK_59C7AA27E3C61F9');
        $this->addSql('ALTER TABLE saved_cart ADD CONSTRAINT fk_saved_cart_owner FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_59c7aa27e3c61f9 RENAME TO uniq_saved_cart_owner');
        $this->addSql('ALTER TABLE conversation ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE conversation ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN conversation.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN conversation.updated_at IS NULL');
        $this->addSql('CREATE INDEX idx_conversation_shop_id ON conversation (shop_id)');
        $this->addSql('ALTER INDEX idx_8a8e26e94d16c4dd RENAME TO idx_conversation_shop');
        $this->addSql('ALTER INDEX uniq_8a8e26e98d9f6d38 RENAME TO uniq_conversation_order');
    }
}
