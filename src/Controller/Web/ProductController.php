<?php

namespace App\Controller\Web;

use App\Entity\Product;
use App\Entity\Shop;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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

        $optionGroups = [];
        foreach ($product->getAttributes() as $attribute) {
            $values = [];
            foreach ($attribute->getValues() as $value) {
                $values[] = [
                    'slug' => $value->getSlug(),
                    'label' => $value->getValue(),
                    'color' => $value->getColorHex(),
                ];
            }

            $optionGroups[] = [
                'slug' => $attribute->getSlug(),
                'name' => $attribute->getName(),
                'type' => $attribute->getInputType(),
                'values' => $values,
            ];
        }

        $variantData = [];
        foreach ($product->getVariants() as $variant) {
            $variantData[] = [
                'id' => $variant->getId(),
                'price' => $variant->getPrice(),
                'promoPrice' => $variant->getPromoPrice(),
                'stock' => $variant->getStock(),
                'isAvailable' => $variant->isAvailable(),
                'configuration' => $variant->getConfiguration(),
                'metadata' => $variant->getMetadata(),
            ];
        }

        $specifications = $this->buildSpecifications($product);

        $adjacentScopeShop = $shopContext instanceof Shop ? $shopContext : null;
        $previousProduct = $this->productRepository->findPreviousProduct($product, $adjacentScopeShop);
        $nextProduct = $this->productRepository->findNextProduct($product, $adjacentScopeShop);

        return $this->render('catalog/product_show.html.twig', [
            'product' => $product,
            'optionGroups' => $optionGroups,
            'variantData' => $variantData,
            'specifications' => $specifications,
            'contextShopId' => $shopContext?->getId(),
            'previousProduct' => $previousProduct,
            'nextProduct' => $nextProduct,
        ]);
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
}
