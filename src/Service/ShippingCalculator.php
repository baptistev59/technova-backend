<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\ShippingMethod;
use App\Entity\Shop;
use App\Repository\ShippingMethodRepository;
use App\Repository\ShippingRateRepository;
use App\Repository\ShippingZoneRepository;

final class ShippingCalculator
{
    public function __construct(
        private readonly ShippingZoneRepository $zoneRepository,
        private readonly ShippingMethodRepository $methodRepository,
        private readonly ShippingRateRepository $rateRepository,
    ) {
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     carrier: string|null,
     *     zone: string,
     *     minDays: int|null,
     *     maxDays: int|null,
     *     price: float,
     * }>
     */
    public function getOptionsForShop(Shop $shop, Address $address, float $weight): array
    {
        $options = [];
        $zones = $this->zoneRepository->findActiveByShop($shop);
        foreach ($zones as $zone) {
            if (!$this->matchesZone($zone->getCountries(), $zone->getPostalCodes(), $address)) {
                continue;
            }

            $methods = $this->methodRepository->findActiveByZone($zone);
            foreach ($methods as $method) {
                $rate = $this->rateRepository->findRateForWeight($method, $weight);
                if (null === $rate) {
                    continue;
                }
                $options[] = $this->buildOption($method, $zone->getName(), $rate->getPrice());
            }
        }

        usort($options, static fn (array $left, array $right) => $left['price'] <=> $right['price']);

        return $options;
    }

    public function buildShippingLine(ShippingMethod $method, Address $address, float $weight): ?array
    {
        $zone = $method->getZone();
        if (!$zone) {
            return null;
        }
        if (!$this->matchesZone($zone->getCountries(), $zone->getPostalCodes(), $address)) {
            return null;
        }

        $rate = $this->rateRepository->findRateForWeight($method, $weight);
        if (null === $rate) {
            return null;
        }

        return [
            'methodId' => $method->getId(),
            'methodName' => $method->getName(),
            'carrier' => $method->getCarrierName(),
            'zone' => $zone->getName(),
            'minDays' => $method->getMinDays(),
            'maxDays' => $method->getMaxDays(),
            'weight' => $weight,
            'price' => $rate->getPrice(),
        ];
    }

    /**
     * @param array<int, string> $countries
     * @param array<int, string>|null $postalCodes
     */
    private function matchesZone(array $countries, ?array $postalCodes, Address $address): bool
    {
        $country = strtoupper((string) $address->getCountry());
        if ([] !== $countries && !in_array($country, $countries, true)) {
            return false;
        }

        if (null === $postalCodes || [] === $postalCodes) {
            return true;
        }

        $postal = strtoupper((string) $address->getPostalCode());
        if ('' === $postal) {
            return false;
        }

        foreach ($postalCodes as $rule) {
            if ($this->matchesPostalRule($postal, $rule)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPostalRule(string $postal, string $rule): bool
    {
        $rule = strtoupper(trim($rule));
        if ('' === $rule) {
            return false;
        }

        if (str_contains($rule, '*')) {
            $prefix = rtrim($rule, '*');
            return '' !== $prefix && str_starts_with($postal, $prefix);
        }

        if (str_contains($rule, '-')) {
            [$start, $end] = array_map('trim', explode('-', $rule, 2));
            if ($start === '' || $end === '') {
                return false;
            }
            if (!ctype_digit($start) || !ctype_digit($end) || !ctype_digit($postal)) {
                return false;
            }
            $numeric = (int) $postal;

            return $numeric >= (int) $start && $numeric <= (int) $end;
        }

        return $postal === $rule;
    }

    /**
     * @return array{id:int, name:string, carrier:string|null, zone:string, minDays:int|null, maxDays:int|null, price:float}
     */
    private function buildOption(ShippingMethod $method, string $zoneName, float $price): array
    {
        return [
            'id' => (int) $method->getId(),
            'name' => $method->getName(),
            'carrier' => $method->getCarrierName(),
            'zone' => $zoneName,
            'minDays' => $method->getMinDays(),
            'maxDays' => $method->getMaxDays(),
            'price' => $price,
        ];
    }
}
