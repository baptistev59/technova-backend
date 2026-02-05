<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\Shop;
use App\Repository\ProductTaxZoneRepository;
use App\Repository\VatRateRepository;

/**
 * Service to resolve VAT rate for a product/country combination.
 * Implements the priority system:
 * 1. ProductTaxZone (zone + tax class matched by delivery country)
 * 2. Legacy TaxZone (product->taxZone) if still configured
 * 3. VatRate global (fallback by country/code)
 * 4. Hard default (20%)
 */
final class VatResolutionService
{
    public function __construct(
        private readonly ProductTaxZoneRepository $productTaxZoneRepository,
        private readonly VatRateRepository $vatRateRepository,
        private readonly float $hardDefault = 20.0,
    ) {
    }

    /**
     * Get the VAT rate for a product and country.
     *
     * Priority order:
    * 1. ProductTaxZone (zone + tax class matched by delivery country)
    * 2. Legacy TaxZone (if product has zone and country in zone)
    * 3. VatRate global (by country + taxClass)
    * 4. Hard default (20%)
     *
     * @param Product      $product The product
     * @param string       $countryCode Country code (e.g., 'FR', 'DE')
     * @param Shop|null    $shop Optional shop context for shop-specific rates
     *
     * @return float The VAT rate as percentage (e.g., 20.0)
     */
    public function getRateForProduct(Product $product, string $countryCode, ?Shop $shop = null): float
    {
        $resolution = $this->resolveVatRateForProduct($product, $countryCode, $shop);

        return $resolution['rate'];
    }

    /**
     * Resolve VAT rate with detailed information about which rule was applied.
     *
     * @param Product      $product The product
     * @param string       $countryCode Country code (e.g., 'FR', 'DE')
     * @param Shop|null    $shop Optional shop context for shop-specific rates
     *
     * @return array{
     *     rate: float,
     *     source: string,
     *     priority: int,
     *     entity?: mixed,
     *     reason?: string
     * }
     */
    public function resolveVatRateForProduct(Product $product, string $countryCode, ?Shop $shop = null): array
    {
        $countryCode = strtoupper($countryCode);

        // 1️⃣ Check ProductTaxZone (HIGHEST PRIORITY)
        $productTaxZone = $this->productTaxZoneRepository->findForProductAndCountry($product, $countryCode);
        if (null !== $productTaxZone && null !== $productTaxZone->getTaxZone() && $productTaxZone->getTaxZone()->isActive()) {
            $taxClass = $productTaxZone->getTaxClass();
            $vatRate = $this->vatRateRepository->findEffectiveRate($countryCode, $shop, $taxClass);
            if (null !== $vatRate) {
                return [
                    'rate' => $vatRate->getRate(),
                    'source' => 'PRODUCT_TAX_ZONE',
                    'priority' => 1,
                    'entity' => $productTaxZone,
                    'reason' => sprintf(
                        'Classe TVA "%s" pour %s via la zone "%s": %.2f%%',
                        $taxClass,
                        $countryCode,
                        $productTaxZone->getTaxZone()->getName(),
                        $vatRate->getRate()
                    ),
                ];
            }

            return [
                'rate' => $productTaxZone->getTaxZone()->getRate(),
                'source' => 'TAX_ZONE',
                'priority' => 1,
                'entity' => $productTaxZone->getTaxZone(),
                'reason' => sprintf(
                    'Taux de la zone TVA "%s" pour %s: %.2f%%',
                    $productTaxZone->getTaxZone()->getName(),
                    $countryCode,
                    $productTaxZone->getTaxZone()->getRate()
                ),
            ];
        }

        // 2️⃣ Legacy TaxZone fallback
        $legacyZone = $product->getTaxZone();
        if (null !== $legacyZone && $legacyZone->isActive() && in_array($countryCode, $legacyZone->getCountryCodes(), true)) {
            $legacyTaxClass = $legacyZone->getTaxClass();
            $legacyVatRate = $this->vatRateRepository->findEffectiveRate($countryCode, $shop, $legacyTaxClass);
            if (null !== $legacyVatRate) {
                return [
                    'rate' => $legacyVatRate->getRate(),
                    'source' => 'TAX_ZONE',
                    'priority' => 2,
                    'entity' => $legacyZone,
                    'reason' => sprintf(
                        'Classe TVA "%s" via zone héritée "%s": %.2f%%',
                        $legacyTaxClass,
                        $legacyZone->getName(),
                        $legacyVatRate->getRate()
                    ),
                ];
            }

            return [
                'rate' => $legacyZone->getRate(),
                'source' => 'TAX_ZONE',
                'priority' => 2,
                'entity' => $legacyZone,
                'reason' => sprintf(
                    'Taux de la zone TVA "%s" pour %s: %.2f%%',
                    $legacyZone->getName(),
                    $countryCode,
                    $legacyZone->getRate()
                ),
            ];
        }

        // 3️⃣ Check VatRate global (LOW PRIORITY)
        $taxClass = $product->getTaxClass();
        $vatRate = $this->vatRateRepository->findEffectiveRate($countryCode, $shop, $taxClass);
        if (null !== $vatRate) {
            return [
                'rate' => $vatRate->getRate(),
                'source' => 'VAT_RATE',
                'priority' => 3,
                'entity' => $vatRate,
                'reason' => sprintf(
                    'Taux global pour %s - classe %s: %.2f%%',
                    $countryCode,
                    $taxClass,
                    $vatRate->getRate()
                ),
            ];
        }

        // 4️⃣ Hard default (MINIMUM)
        return [
            'rate' => $this->hardDefault,
            'source' => 'DEFAULT',
            'priority' => 4,
            'reason' => sprintf('Taux par défaut: %.2f%%', $this->hardDefault),
        ];
    }

    /**
     * Get VAT coverage report for a product.
     *
     * Shows:
     * - All countries covered by zone
    * - All countries covered by product tax zones
     * - All countries with global VatRate
     * - Missing countries (no coverage)
     *
     * @param Product      $product The product
     * @param Shop|null    $shop Optional shop context
     *
     * @return array{
     *     covered_countries: string[],
     *     missing_countries: string[],
     *     zones_used: array,
     *     overrides: array,
     *     global_vats: array,
     *     by_country: array
     * }
     */
    public function getProductVatCoverage(Product $product, ?Shop $shop = null): array
    {
        // Common countries to check
        $allCountries = [
            'FR', 'DE', 'IT', 'ES', 'BE', 'NL', 'AT', 'LU',
            'GR', 'PT', 'PL', 'CZ', 'HU', 'RO', 'BG', 'HR',
            'IE', 'GB', 'CH', 'SE', 'NO', 'DK', 'FI',
            'US', 'CA', 'MX', 'BR', 'AU', 'JP', 'CN', 'IN',
        ];

        $coveredCountries = [];
        $zoneInfo = [];
        $globalVatInfo = [];
        $byCountry = [];

        // Product tax zones information
        $productZones = $this->productTaxZoneRepository->findForProduct($product);
        foreach ($productZones as $productZone) {
            $zone = $productZone->getTaxZone();
            if (null === $zone || !$zone->isActive()) {
                continue;
            }

            $zoneInfo[] = [
                'name' => $zone->getName(),
                'code' => $zone->getCode(),
                'rate' => $zone->getRate(),
                'tax_class' => $productZone->getTaxClass(),
                'countries' => $zone->getCountryCodes(),
            ];

            foreach ($zone->getCountryCodes() as $country) {
                if (!in_array($country, $coveredCountries, true)) {
                    $coveredCountries[] = $country;
                }
            }
        }

        // Sort and deduplicate
        $coveredCountries = array_values(array_unique($coveredCountries));
        sort($coveredCountries);

        // Missing countries
        $missingCountries = array_diff($allCountries, $coveredCountries);
        sort($missingCountries);

        // Detail by country
        foreach ($coveredCountries as $country) {
            $resolution = $this->resolveVatRateForProduct($product, $country, $shop);
            $byCountry[$country] = [
                'rate' => $resolution['rate'],
                'source' => $resolution['source'],
                'reason' => $resolution['reason'] ?? '',
            ];
        }

        return [
            'covered_countries' => $coveredCountries,
            'missing_countries' => array_values($missingCountries),
            'zones_used' => $zoneInfo,
            'overrides' => [],
            'global_vats' => $globalVatInfo,
            'by_country' => $byCountry,
        ];
    }

    /**
     * Calculate effective tax class for a product in a country.
    * Uses ProductTaxZone if available, otherwise Product->taxClass.
     *
     * @param Product $product The product
     * @param string  $countryCode Country code
     *
     * @return string Tax class (STANDARD, REDUCED, ZERO)
     */
    public function getTaxClassForProduct(Product $product, string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        // Check ProductTaxZone
        $productTaxZone = $this->productTaxZoneRepository->findForProductAndCountry($product, $countryCode);
        if (null !== $productTaxZone && null !== $productTaxZone->getTaxZone()) {
            return $productTaxZone->getTaxClass();
        }

        // Legacy TaxZone
        $legacyZone = $product->getTaxZone();
        if (null !== $legacyZone && in_array($countryCode, $legacyZone->getCountryCodes(), true)) {
            return $legacyZone->getTaxClass();
        }

        // Default to product tax class
        return $product->getTaxClass();
    }

    /**
     * Verify a product has complete VAT configuration.
     * Checks if product is properly configured for a country.
     *
     * @param Product      $product The product
     * @param string       $countryCode Country code
     *
     * @return array{
     *     is_configured: bool,
     *     has_override: bool,
     *     has_zone: bool,
     *     has_global_vat: bool,
     *     rate: float,
     *     issues: string[]
     * }
     */
    public function validateProductVatConfig(Product $product, string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $issues = [];

        $productTaxZone = $this->productTaxZoneRepository->findForProductAndCountry($product, $countryCode);
        $hasZone = null !== $productTaxZone && null !== $productTaxZone->getTaxZone() && $productTaxZone->getTaxZone()->isActive();

        $taxClass = $this->getTaxClassForProduct($product, $countryCode);
        $vatRate = $this->vatRateRepository->findEffectiveRate($countryCode, null, $taxClass);
        $hasGlobalVat = null !== $vatRate;

        $resolution = $this->resolveVatRateForProduct($product, $countryCode);
        $rate = $resolution['rate'];
        $isConfigured = 'DEFAULT' !== $resolution['source'];

        if (!$isConfigured) {
            $issues[] = sprintf('Aucune configuration de TVA pour %s (utilise le défaut 20%%)', $countryCode);
        }

        if (!$hasZone && !$hasGlobalVat) {
            $issues[] = sprintf('Aucune source de taux (zone ou taux global) pour %s', $countryCode);
        }

        return [
            'is_configured' => $isConfigured,
            'has_override' => false,
            'has_zone' => $hasZone,
            'has_global_vat' => $hasGlobalVat,
            'rate' => $rate,
            'issues' => $issues,
        ];
    }
}
