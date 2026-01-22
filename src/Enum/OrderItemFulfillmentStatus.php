<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderItemFulfillmentStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function isShipped(): bool
    {
        return self::Shipped === $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Shipped => 'Expédié',
            self::Cancelled => 'Annulé',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Shipped, self::Cancelled], true);
    }
}
