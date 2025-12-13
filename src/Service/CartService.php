<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\SavedCart;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\SavedCartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Gère le panier stocké en session (et son miroir base de données) avec
 * prise en charge des variantes.
 */
class CartService
{
    private const SESSION_KEY = 'cart.items';
    private const STATE_VERSION = 3;

    /**
     * @var array{version:int,lines:array<string,array{product_id:int,variant_id:int|null,quantity:int,unit_price_override:float|null}>}
     */
    private array $state = ['version' => self::STATE_VERSION, 'lines' => []];
    private bool $initialized = false;
    private bool $dirty = false;

    private ?SessionInterface $session;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepository $productRepository,
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly SavedCartRepository $savedCartRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
        $this->session = $this->requestStack->getSession();
    }

    public function addProduct(
        Product $product,
        int $quantity = 1,
        ?ProductVariant $variant = null,
        ?int $variantId = null,
        ?float $unitPriceOverride = null,
    ): void {
        $this->ensureInitialized();
        $variant = $this->resolveVariant($product, $variant, $variantId);

        $lineKey = $this->buildLineKey($product->getId(), $variant?->getId());
        $line = $this->state['lines'][$lineKey] ?? [
            'product_id' => $product->getId(),
            'variant_id' => $variant?->getId(),
            'quantity' => 0,
            'unit_price_override' => null,
        ];

        $line['variant_id'] = $variant?->getId();
        $line['quantity'] = $this->clampQuantity(
            $line['quantity'] + max(1, $quantity),
            $this->resolveMaxStock($product, $variant)
        );
        if ($unitPriceOverride !== null) {
            $line['unit_price_override'] = $unitPriceOverride;
        } elseif (!array_key_exists('unit_price_override', $line)) {
            $line['unit_price_override'] = null;
        }

        $this->state['lines'][$lineKey] = $line;
        $this->dirty = true;
        $this->persistState();
    }

    public function setProductQuantity(
        Product $product,
        int $quantity,
        ?ProductVariant $variant = null,
        ?int $variantId = null
    ): void {
        $this->ensureInitialized();
        $variant = $this->resolveVariant($product, $variant, $variantId);
        $lineKey = $this->buildLineKey($product->getId(), $variant?->getId());
        $existing = $this->state['lines'][$lineKey] ?? null;
        $unitPriceOverride = $existing['unit_price_override'] ?? null;

        $quantity = $this->clampQuantity(
            max(0, $quantity),
            $this->resolveMaxStock($product, $variant)
        );

        if ($quantity <= 0) {
            unset($this->state['lines'][$lineKey]);
        } else {
            $this->state['lines'][$lineKey] = [
                'product_id' => $product->getId(),
                'variant_id' => $variant?->getId(),
                'quantity' => $quantity,
                'unit_price_override' => $unitPriceOverride !== null ? (float) $unitPriceOverride : null,
            ];
        }

        $this->dirty = true;
        $this->persistState();
    }

    public function removeProduct(
        Product $product,
        ?ProductVariant $variant = null,
        ?int $variantId = null
    ): void {
        $this->ensureInitialized();
        $variant = $this->resolveVariant($product, $variant, $variantId);
        $lineKey = $this->buildLineKey($product->getId(), $variant?->getId());

        if (isset($this->state['lines'][$lineKey])) {
            unset($this->state['lines'][$lineKey]);
            $this->dirty = true;
            $this->persistState();
        }
    }

    public function clear(): void
    {
        $this->ensureInitialized();
        $this->state = $this->freshState();
        $this->dirty = true;
        $this->persistState();
    }

    /**
     * @return array{
     *     items: array<int, array{
     *         lineId: string,
     *         product: Product,
     *         variant: ProductVariant|null,
     *         variantLabel: string|null,
     *         unitPrice: float,
     *         basePrice: float,
     *         promoPrice: float|null,
     *         quantity: int,
     *         stock: int,
     *         lineTotal: float,
     *     }>,
     *     total: float,
     *     totalQuantity: int
     * }
     */
    public function getSummary(): array
    {
        $this->ensureInitialized();

        $items = [];
        $total = 0.0;
        $totalQuantity = 0;
        $stateChanged = false;

        foreach ($this->state['lines'] as $lineId => $line) {
            $product = $this->productRepository->find($line['product_id']);
            if (!$product) {
                unset($this->state['lines'][$lineId]);
                $stateChanged = true;
                continue;
            }

            $variant = null;
            $variantLabel = null;
            $basePrice = (float) $product->getPrice();
            $promoPrice = $product->getPromoPrice();
            $stock = $product->getStock();
            $unitPriceOverride = array_key_exists('unit_price_override', $line) && $line['unit_price_override'] !== null
                ? (float) $line['unit_price_override']
                : null;

            if ($line['variant_id']) {
                $variant = $this->productVariantRepository->find($line['variant_id']);
                if (!$variant || $variant->getProduct()?->getId() !== $product->getId()) {
                    unset($this->state['lines'][$lineId]);
                    $stateChanged = true;
                    continue;
                }
                $basePrice = $variant->getPrice();
                $promoPrice = $variant->getPromoPrice();
                $stock = $variant->getStock();
                $variantLabel = $this->formatVariantLabel($variant);
            }

            $quantity = $line['quantity'];
            if ($stock >= 0 && $quantity > $stock) {
                $quantity = $stock;
                $this->state['lines'][$lineId]['quantity'] = $quantity;
                $stateChanged = true;
            }

            if ($quantity <= 0) {
                unset($this->state['lines'][$lineId]);
                $stateChanged = true;
                continue;
            }

            $referencePrice = $promoPrice ?? $basePrice;
            $unitPrice = $referencePrice;
            if ($unitPriceOverride !== null) {
                $unitPrice = $unitPriceOverride;
            }
            $appliedDiscount = max(0, $referencePrice - $unitPrice);
            $lineTotal = $unitPrice * $quantity;

            $items[] = [
                'lineId' => $lineId,
                'product' => $product,
                'variant' => $variant,
                'variantLabel' => $variantLabel,
                'unitPrice' => $unitPrice,
                'basePrice' => $basePrice,
                'promoPrice' => $promoPrice,
                'appliedDiscount' => $appliedDiscount,
                'quantity' => $quantity,
                'stock' => $stock,
                'lineTotal' => $lineTotal,
            ];

            $total += $lineTotal;
            $totalQuantity += $quantity;
        }

        if ($stateChanged) {
            $this->dirty = true;
            $this->persistState();
        }

        return [
            'items' => $items,
            'total' => $total,
            'totalQuantity' => $totalQuantity,
        ];
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $stored = $this->session?->get(self::SESSION_KEY);
        $state = $this->normalizeState($stored);

        if (empty($state['lines'])) {
            $user = $this->getUser();
            if ($user) {
                $saved = $this->savedCartRepository->findOneBy(['owner' => $user]);
                if ($saved instanceof SavedCart) {
                    $state = $this->normalizeState($saved->getItems());
                }
            }
        }

        $this->state = $state;
        $this->initialized = true;
        $this->dirty = false;

        if ($this->session) {
            $this->session->set(self::SESSION_KEY, $this->state);
        }
    }

    private function resolveVariant(
        Product $product,
        ?ProductVariant $variant,
        ?int $variantId = null
    ): ?ProductVariant {
        if ($variant && $variant->getProduct()?->getId() !== $product->getId()) {
            throw new \InvalidArgumentException('La variante ne correspond pas au produit.');
        }

        if (!$variant && $variantId) {
            $variant = $this->productVariantRepository->find($variantId);
            if ($variant && $variant->getProduct()?->getId() !== $product->getId()) {
                $variant = null;
            }
        }

        return $variant;
    }

    private function buildLineKey(int $productId, ?int $variantId): string
    {
        return sprintf('%d:%s', $productId, $variantId ?? 'base');
    }

    private function resolveMaxStock(Product $product, ?ProductVariant $variant): int
    {
        return $variant?->getStock() ?? $product->getStock();
    }

    private function clampQuantity(int $quantity, int $max): int
    {
        if ($max > 0) {
            return min($quantity, $max);
        }

        return $quantity;
    }

    private function formatVariantLabel(ProductVariant $variant): ?string
    {
        $metadata = $variant->getMetadata() ?? [];

        if (empty($metadata)) {
            return null;
        }

        return implode(' • ', array_values($metadata));
    }

    /**
     * @param mixed $rawState
     * @return array{version:int,lines:array<string,array{product_id:int,variant_id:int|null,quantity:int,unit_price_override:float|null}>}
     */
    private function normalizeState(mixed $rawState): array
    {
        if (!is_array($rawState)) {
            return $this->freshState();
        }

        if (isset($rawState['version'], $rawState['lines']) && (int) $rawState['version'] >= 2) {
            return [
                'version' => self::STATE_VERSION,
                'lines' => $this->sanitizeLines($rawState['lines']),
            ];
        }

        // Legacy format : [productId => quantity]
        if ($this->looksLikeLegacy($rawState)) {
            $lines = [];
            foreach ($rawState as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = max(0, (int) $quantity);
                if ($productId > 0 && $quantity > 0) {
                    $lineId = $this->buildLineKey($productId, null);
                    $lines[$lineId] = [
                        'product_id' => $productId,
                        'variant_id' => null,
                        'quantity' => $quantity,
                        'unit_price_override' => null,
                    ];
                }
            }

            return [
                'version' => self::STATE_VERSION,
                'lines' => $lines,
            ];
        }

        return $this->freshState();
    }

    /**
     * @param array<string, mixed> $lines
     * @return array<string, array{product_id:int,variant_id:int|null,quantity:int,unit_price_override:float|null}>
     */
    private function sanitizeLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $lineId => $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $variantId = array_key_exists('variant_id', $line) ? ($line['variant_id'] !== null ? (int) $line['variant_id'] : null) : null;
            $quantity = isset($line['quantity']) ? max(0, (int) $line['quantity']) : 0;
            $override = array_key_exists('unit_price_override', $line) && $line['unit_price_override'] !== null
                ? (float) $line['unit_price_override']
                : null;

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[$lineId] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price_override' => $override,
            ];
        }

        return $normalized;
    }

    private function looksLikeLegacy(array $raw): bool
    {
        foreach ($raw as $key => $value) {
            if (!is_numeric($key) || !is_numeric($value)) {
                return false;
            }
        }

        return !empty($raw);
    }

    private function freshState(): array
    {
        return ['version' => self::STATE_VERSION, 'lines' => []];
    }

    private function persistState(): void
    {
        if (!$this->dirty) {
            return;
        }

        if ($this->session) {
            $this->session->set(self::SESSION_KEY, $this->state);
        }

        $this->syncSavedCart();
        $this->dirty = false;
    }

    private function syncSavedCart(): void
    {
        $user = $this->getUser();
        if (!$user) {
            return;
        }

        $lines = $this->state['lines'];
        $saved = $this->savedCartRepository->findOneBy(['owner' => $user]);

        if (empty($lines)) {
            if ($saved) {
                $this->entityManager->remove($saved);
                $this->entityManager->flush();
            }
            return;
        }

        if (!$saved) {
            $saved = (new SavedCart())->setOwner($user);
        }

        $saved
            ->setItems([
                'version' => self::STATE_VERSION,
                'lines' => $lines,
            ])
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($saved);
        $this->entityManager->flush();
    }

    private function getUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }
}
