<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentType: string
{
    case INVOICE = 'invoice';
    case DELIVERY = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => 'Facture',
            self::DELIVERY => 'Bon de livraison',
        };
    }
}
