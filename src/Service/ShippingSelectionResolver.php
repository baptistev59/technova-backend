<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Shop;

final class ShippingSelectionResolver
{
    public function __construct(
        private readonly ShippingCalculator $shippingCalculator,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array{
     *     shop: Shop,
     *     weight: float,
     *     options: array<int, array{id:int,name:string,carrier:?string,zone:string,minDays:?int,maxDays:?int,price:float}>,
     *     defaultId: int|null,
     * }>
     */
    public function buildOptions(array $items, Address $address): array
    {
        $grouped = $this->groupItemsByShop($items);
        $result = [];

        foreach ($grouped as $shopId => $data) {
            $options = $this->shippingCalculator->getOptionsForShop($data['shop'], $address, $data['weight']);
            $result[$shopId] = [
                'shop' => $data['shop'],
                'weight' => $data['weight'],
                'options' => $options,
                'defaultId' => $options[0]['id'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $selection
     *
     * @return array{valid:bool,message:string,lines:array<int, array<string, mixed>>,shippingTotal:float}
     */
    public function resolveSelection(array $items, Address $address, array $selection): array
    {
        $grouped = $this->groupItemsByShop($items);
        $lines = [];
        $total = 0.0;

        foreach ($grouped as $shopId => $data) {
            $methodId = isset($selection[$shopId]) ? (int) $selection[$shopId] : null;
            if (!$methodId) {
                return [
                    'valid' => false,
                    'message' => 'Sélectionne un mode de livraison pour chaque boutique.',
                    'lines' => [],
                    'shippingTotal' => 0.0,
                ];
            }

            $options = $this->shippingCalculator->getOptionsForShop($data['shop'], $address, $data['weight']);
            $option = null;
            foreach ($options as $optionRow) {
                if ($optionRow['id'] === $methodId) {
                    $option = $optionRow;
                    break;
                }
            }
            if (null === $option) {
                return [
                    'valid' => false,
                    'message' => 'Mode de livraison invalide.',
                    'lines' => [],
                    'shippingTotal' => 0.0,
                ];
            }

            $lines[] = [
                'shopId' => (int) $data['shop']->getId(),
                'shopName' => $data['shop']->getName(),
                'methodId' => $option['id'],
                'methodName' => $option['name'],
                'carrier' => $option['carrier'],
                'zone' => $option['zone'],
                'minDays' => $option['minDays'],
                'maxDays' => $option['maxDays'],
                'weight' => $data['weight'],
                'price' => $option['price'],
            ];
            $total += $option['price'];
        }

        return [
            'valid' => true,
            'message' => '',
            'lines' => $lines,
            'shippingTotal' => $total,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, array{shop:Shop, weight:float}>
     */
    private function groupItemsByShop(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $product = $item['product'];
            $shop = $product->getShop();
            if (!$shop) {
                continue;
            }
            $shopId = (string) $shop->getId();
            $weight = $this->resolveItemWeight($product, $item['variant'] ?? null);
            $grouped[$shopId]['shop'] = $shop;
            $grouped[$shopId]['weight'] = ($grouped[$shopId]['weight'] ?? 0) + ($weight * (int) $item['quantity']);
        }

        return $grouped;
    }

    private function resolveItemWeight(Product $product, ?ProductVariant $variant): float
    {
        $weight = $variant?->getWeight();
        if (null !== $weight) {
            return $weight;
        }

        return $product->getWeight();
    }
}
