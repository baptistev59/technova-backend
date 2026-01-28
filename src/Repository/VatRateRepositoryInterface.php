<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VatRate;
use App\Entity\Shop;

interface VatRateRepositoryInterface
{
    public function findEffectiveRate(string $countryCode, ?Shop $shop = null, string $code = 'STANDARD'): ?VatRate;
}
