<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Product;
use App\Entity\Shop;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route de fiche produit. Symfony injecte directement l'entité via le slug.
 */
class ProductController extends AbstractController
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    #[Route('/produit/{slug}', name: 'product_show')]
    public function show(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Product $product): Response
    {
        $shopContext = null;
        $requestedShopId = $request->query->get('shop');
        if ($requestedShopId && $product->getShop() && (int) $requestedShopId === $product->getShop()->getId()) {
            $shopContext = $product->getShop();
        }

        $optionGroups = $this->buildOptionGroupsFromSelections($product);
        if ([] === $optionGroups) {
            $optionGroups = $this->buildOptionGroupsFromLegacyAttributes($product);
        }

        $variantData = $this->buildVariantPayload($product);

        $specifications = $this->buildSpecifications($product);

        $adjacentScopeShop = $shopContext instanceof Shop ? $shopContext : null;
        $previousProduct = $this->productRepository->findPreviousProduct($product, $adjacentScopeShop);
        $nextProduct = $this->productRepository->findNextProduct($product, $adjacentScopeShop);

        $groupedStock = $this->computeGroupedStock($product);
        $bundleComponents = $this->buildBundleComponents($product);
        $bundlePriceRange = $this->computeBundlePriceRange($product, $bundleComponents);

        return $this->render('catalog/product_show.html.twig', [
            'product' => $product,
            'optionGroups' => $optionGroups,
            'variantData' => $variantData,
            'bundleComponents' => $bundleComponents,
            'bundlePriceRange' => $bundlePriceRange,
            'specifications' => $specifications,
            'grouped_stock' => $groupedStock,
            'contextShopId' => $shopContext?->getId(),
            'previousProduct' => $previousProduct,
            'nextProduct' => $nextProduct,
        ]);
    }

    #[Route('/produit/{id}/modal', name: 'product_modal', methods: ['GET'])]
    public function modal(Product $product): Response
    {
        $optionGroups = $this->buildOptionGroupsFromSelections($product);
        if ([] === $optionGroups) {
            $optionGroups = $this->buildOptionGroupsFromLegacyAttributes($product);
        }

        return $this->render('catalog/fragment/product_modal.html.twig', [
            'product' => $product,
            'variantData' => $this->buildVariantPayload($product),
            'optionGroups' => $optionGroups,
            'specifications' => $this->buildSpecifications($product),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOptionGroupsFromSelections(Product $product): array
    {
        $groups = [];
        foreach ($product->getAttributeSelections() as $selection) {
            $attribute = $selection->getAttribute();
            if (!$attribute) {
                continue;
            }

            $values = [];
            foreach ($selection->getValues() as $value) {
                $values[] = [
                    'slug' => $value->getValue() ?? (string) $value->getId(),
                    'label' => $value->getLabel() ?? $value->getValue(),
                    'color' => null,
                ];
            }

            if ([] === $values) {
                continue;
            }

            usort($values, static fn (array $a, array $b) => strcmp((string) $a['label'], (string) $b['label']));

            $groups[] = [
                'slug' => $attribute->getSlug() ?? ('attribute_'.$attribute->getId()),
                'name' => $attribute->getName() ?? 'Attribut',
                'type' => $attribute->getInputType(),
                'values' => $values,
            ];
        }

        usort($groups, static fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOptionGroupsFromLegacyAttributes(Product $product): array
    {
        $groups = [];
        foreach ($product->getAttributes() as $attribute) {
            $values = [];
            foreach ($attribute->getValues() as $value) {
                $values[] = [
                    'slug' => $value->getSlug(),
                    'label' => $value->getValue(),
                    'color' => $value->getColorHex(),
                ];
            }

            if ([] === $values) {
                continue;
            }

            $groups[] = [
                'slug' => $attribute->getSlug(),
                'name' => $attribute->getName(),
                'type' => $attribute->getInputType(),
                'values' => $values,
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    private function buildSpecifications(Product $product): array
    {
        return [
            'Catégorie' => $product->getCategory()?->getName() ?? 'N/A',
            'Marque' => $product->getBrand()?->getName() ?? 'TechNova',
            'Boutique' => $product->getShop()?->getName() ?? 'Marketplace',
            'SKU' => $product->getSku() ?? 'N/A',
            'Code-barres' => $product->getBarcode() ?? 'N/A',
        ];
    }

    private function computeGroupedStock(Product $product): ?int
    {
        if ('grouped' !== $product->getType()) {
            return null;
        }

        $stocks = [];
        foreach ($product->getBundleItems() as $item) {
            $component = $item->getComponent();
            if (!$component) {
                continue;
            }

            $componentStock = 0;
            if ($component->getVariants()->count() > 0) {
                foreach ($component->getVariants() as $variant) {
                    $componentStock += $variant->getStock();
                }
            } else {
                $componentStock = $component->getStock();
            }

            if ($componentStock > 0) {
                $stocks[] = $componentStock;
            }
        }

        if ([] === $stocks) {
            return null;
        }

        sort($stocks);

        return $stocks[0];
    }

    /**
     * @return array{min: float|null, max: float|null, label: string}
     */
    private function computeProductPriceRange(Product $product, array $visited = []): array
    {
        $prices = $this->collectEffectivePrices($product, $visited);
        if ([] === $prices) {
            return ['min' => null, 'max' => null, 'label' => '—'];
        }

        sort($prices, SORT_NUMERIC);
        $min = $prices[0];
        $max = $prices[count($prices) - 1];
        $label = $min === $max
            ? number_format($min, 2, ',', ' ').' €'
            : sprintf('%s – %s', number_format($min, 2, ',', ' '), number_format($max, 2, ',', ' '));

        return [
            'min' => $min,
            'max' => $max,
            'label' => $label,
        ];
    }

    /**
     * @return float[]
     */
    private function collectEffectivePrices(Product $product, array $visited = []): array
    {
        $prices = [];
        $productId = $product->getId();
        if (null !== $productId) {
            if (in_array($productId, $visited, true)) {
                return [];
            }
            $visited[] = $productId;
        }

        if ('grouped' === $product->getType()) {
            foreach ($product->getBundleItems() as $item) {
                $component = $item->getComponent();
                if ($component) {
                    $prices = array_merge($prices, $this->collectEffectivePrices($component, $visited));
                }
            }

            return $prices;
        }

        if ($product->getVariants()->count() > 0) {
            foreach ($product->getVariants() as $variant) {
                $price = $variant->getPromoPrice() ?: $variant->getPrice();
                if ($price > 0) {
                    $prices[] = $price;
                }
            }
        } else {
            $price = $product->getPromoPrice();
            if (null === $price || $price <= 0 || $price >= $product->getPrice()) {
                $price = $product->getPrice();
            }
            if ($price > 0) {
                $prices[] = $price;
            }
        }

        return $prices;
    }

    private function buildVariantPayload(Product $product): array
    {
        $variants = [];
        foreach ($product->getVariants() as $variant) {
            $variants[] = [
                'id' => $variant->getId(),
                'price' => $variant->getPrice(),
                'promoPrice' => $variant->getPromoPrice(),
                'stock' => $variant->getStock(),
                'metadata' => $variant->getMetadata() ?? [],
            ];
        }

        return $variants;
    }

    private function computeComponentStock(Product $product): ?int
    {
        if ($product->getVariants()->count() > 0) {
            $total = 0;
            foreach ($product->getVariants() as $variant) {
                $total += $variant->getStock();
            }

            return $total > 0 ? $total : null;
        }

        return $product->getStock() > 0 ? $product->getStock() : null;
    }

    private function buildBundleComponents(Product $product): array
    {
        if ('grouped' !== $product->getType()) {
            return [];
        }

        $components = [];
        foreach ($product->getBundleItems() as $item) {
            $component = $item->getComponent();
            if (!$component) {
                continue;
            }

            $components[] = [
                'id' => $component->getId(),
                'name' => $component->getName(),
                'slug' => $component->getSlug(),
                'sku' => $component->getSku(),
                'type' => $component->getType() ?? 'simple',
                'typeLabel' => $this->humanizeProductType($component->getType() ?? 'simple'),
                'priceRange' => $this->computeProductPriceRange($component),
                'stock' => $this->computeComponentStock($component),
                'variants' => $this->buildVariantPayload($component),
                'basePrice' => $component->getPrice(),
                'promoPrice' => $component->getPromoPrice(),
                'modalUrl' => $this->generateUrl('product_modal', ['id' => $component->getId()]),
            ];
        }

        return $components;
    }

    /**
     * @param array<int, array<string, mixed>>|null $components
     */
    private function computeBundlePriceRange(Product $product, ?array $components = null): ?array
    {
        if ('grouped' !== $product->getType()) {
            return null;
        }

        $components ??= $this->buildBundleComponents($product);
        if ([] === $components) {
            return null;
        }

        $globalMin = null;
        $globalMax = null;
        foreach ($components as $component) {
            $range = $component['priceRange'] ?? null;
            if (!is_array($range)) {
                continue;
            }
            if (isset($range['min']) && null !== $range['min']) {
                $value = (float) $range['min'];
                $globalMin = null === $globalMin ? $value : min($globalMin, $value);
            }
            if (isset($range['max']) && null !== $range['max']) {
                $value = (float) $range['max'];
                $globalMax = null === $globalMax ? $value : max($globalMax, $value);
            }
        }

        if (null === $globalMin && null === $globalMax) {
            return null;
        }

        $formatter = static fn (float $value): string => number_format($value, 2, ',', ' ').' €';

        $label = '—';
        if (null !== $globalMin && null !== $globalMax) {
            $label = $globalMin === $globalMax
                ? $formatter($globalMin)
                : sprintf('%s – %s', $formatter($globalMin), $formatter($globalMax));
        } elseif (null !== $globalMin) {
            $label = $formatter($globalMin);
        } elseif (null !== $globalMax) {
            $label = $formatter($globalMax);
        }

        return [
            'min' => $globalMin,
            'max' => $globalMax,
            'label' => $label,
        ];
    }

    private function humanizeProductType(?string $type): string
    {
        return match ($type) {
            'variable' => 'Produit variable',
            'grouped' => 'Produit groupé',
            default => 'Produit simple',
        };
    }
}
