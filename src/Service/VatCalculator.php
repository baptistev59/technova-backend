<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VatRateRepositoryInterface;
use App\Entity\Shop;

final class VatCalculator
{
    private VatRateRepositoryInterface $repository;
    private float $defaultRate = 20.0;
    private string $roundingMode = 'per_line';
    private int $scale = 2;

    public function __construct(VatRateRepositoryInterface $repository, float $defaultRate = 20.0, string $roundingMode = 'per_line', int $scale = 2)
    {
        $this->repository = $repository;
        $this->defaultRate = $defaultRate;
        $this->roundingMode = $roundingMode;
        $this->scale = $scale;
    }

    public function getRatePercent(string $countryCode, ?Shop $shop = null, string $code = 'STANDARD'): float
    {
        $rateEntity = $this->repository->findEffectiveRate($countryCode, $shop, $code);

        return null !== $rateEntity ? $rateEntity->getRate() : $this->defaultRate;
    }

    /**
     * Calculate tax amount from net price.
     * Uses integer cents internally to avoid float imprecision.
     * Rounding strategy controlled by constructor params.
     */
    public function calculateTaxFromNet(float $net, string $countryCode, ?Shop $shop = null, string $code = 'STANDARD'): float
    {
        $percent = $this->getRatePercent($countryCode, $shop, $code);

        // work in cents to avoid float issues
        $netCents = (int) round($net * (10 ** $this->scale));

        $taxCents = (int) round($netCents * ($percent / 100.0));

        $tax = $taxCents / (10 ** $this->scale);

        return round($tax, $this->scale, PHP_ROUND_HALF_UP);
    }

    public function calculateGrossFromNet(float $net, string $countryCode, ?Shop $shop = null, string $code = 'STANDARD'): float
    {
        $tax = $this->calculateTaxFromNet($net, $countryCode, $shop, $code);

        return round($net + $tax, $this->scale, PHP_ROUND_HALF_UP);
    }

    public function calculateNetFromGross(float $gross, string $countryCode, ?Shop $shop = null, string $code = 'STANDARD'): float
    {
        $percent = $this->getRatePercent($countryCode, $shop, $code);
        $divider = 1 + ($percent / 100.0);

        $net = $gross / $divider;

        return round($net, $this->scale, PHP_ROUND_HALF_UP);
    }
}
