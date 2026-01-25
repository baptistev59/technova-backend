<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing indexes for frequently queried columns
 */
final class Version20260125160700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on wishlist.user_id and conversation.shop_id for query performance';
    }

    public function up(Schema $schema): void
    {
        // Index for wishlist filtering by user (frequent query in WishlistController::list() and count())
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_wishlist_user_id ON wishlist(user_id)');
        
        // Index for conversation filtering by shop (frequent query in conversations views)
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_conversation_shop_id ON conversation(shop_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_wishlist_user_id');
        $this->addSql('DROP INDEX IF EXISTS idx_conversation_shop_id');
    }
}
