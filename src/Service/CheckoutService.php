<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\CustomerOrder;
use App\Entity\CustomerOrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Convertit un panier en commande persistée.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderMailer $orderMailer,
        private readonly ProductRepository $productRepository,
        private readonly ProductVariantRepository $productVariantRepository,
        #[Autowire(service: 'state_machine.customer_order')]
        private readonly WorkflowInterface $orderWorkflow,
    ) {
    }

    public function createOrder(User $user, Address $shippingAddress): CustomerOrder
    {
        $summary = $this->cartService->getSummary();
        if (empty($summary['items'])) {
            throw new \RuntimeException('Panier vide, impossible de créer la commande.');
        }

        $this->assertStockAvailability($summary['items']);

        $order = (new CustomerOrder())
            ->setOwner($user)
            ->setReference($this->generateReference())
            ->setStatus(CustomerOrder::STATUS_PENDING)
            ->setCurrency('EUR')
            ->setTotalAmount($this->formatAmount($summary['total']))
            ->setShippingAddress($this->addressToArray($shippingAddress))
            ->setBillingAddress($this->addressToArray($shippingAddress));

        foreach ($summary['items'] as $cartLine) {
            $product = $cartLine['product'];
            $variant = $cartLine['variant'];
            $item = (new CustomerOrderItem())
                ->setProductId($product->getId())
                ->setProductName($product->getName())
                ->setProductImage($this->resolveProductImage($product))
                ->setShopId($product->getShop()?->getId())
                ->setQuantity($cartLine['quantity'])
                ->setVariantId($variant ? $variant->getId() : null)
                ->setUnitPrice($this->formatAmount((float) $cartLine['unitPrice']))
                ->setLineTotal($this->formatAmount((float) $cartLine['lineTotal']));

            $order->addItem($item);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    public function attachPaymentSession(CustomerOrder $order, string $sessionId): void
    {
        $order->setPaymentSessionId($sessionId);
        $this->entityManager->flush();
    }

    public function finalizePayment(CustomerOrder $order, ?string $paymentIntentId = null, array $context = []): void
    {
        if (!$this->orderWorkflow->can($order, 'pay')) {
            return;
        }

        $order->setPaidAt($order->getPaidAt() ?? new \DateTimeImmutable());
        $order->setPaymentIntentId($paymentIntentId);
        $this->orderWorkflow->apply($order, 'pay', array_merge([
            'triggered_by' => 'checkout:finalize',
        ], $context));

        $this->decrementStockFromOrder($order);
        $this->entityManager->flush();
        $this->orderMailer->sendConfirmation($order);
    }

    public function cancelOrder(CustomerOrder $order, array $context = []): void
    {
        if (!$this->orderWorkflow->can($order, 'cancel')) {
            return;
        }

        $this->orderWorkflow->apply($order, 'cancel', array_merge([
            'triggered_by' => 'checkout:cancel',
        ], $context));
        $this->entityManager->flush();
    }

    private function generateReference(): string
    {
        return sprintf('TN-%s-%s', date('Ymd'), substr((string) random_int(100000, 999999), -6));
    }

    private function addressToArray(Address $address): array
    {
        return [
            'label' => $address->getLabel(),
            'addressLine1' => $address->getAddressLine1(),
            'addressLine2' => $address->getAddressLine2(),
            'postalCode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'state' => $address->getState(),
            'country' => $address->getCountry(),
        ];
    }

    private function formatAmount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function resolveProductImage(Product $product): ?string
    {
        $image = $product->getImages()->first();
        return $image ? $image->getUrl() : null;
    }

    /**
     * @param array<int, array<string, mixed>> $cartItems
     */
    private function assertStockAvailability(array $cartItems): void
    {
        foreach ($cartItems as $cartLine) {
            $product = $cartLine['product'];
            $variant = $cartLine['variant'];
            $quantity = (int) $cartLine['quantity'];
            $this->guardStockLevel($product, $variant, $quantity);
        }
    }

    private function guardStockLevel(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $available = $variant ? $variant->getStock() : $product->getStock();
        if ($available < $quantity || $available <= 0) {
            $name = $variant ? sprintf('%s (%s)', $product->getName(), implode(' / ', array_values($variant->getMetadata() ?? []))) : $product->getName();
            throw new \RuntimeException(sprintf('Stock insuffisant pour %s.', $name));
        }
    }

    private function decrementStockFromOrder(CustomerOrder $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $this->productRepository->find($item->getProductId());
            if (!$product) {
                continue;
            }

            $variant = null;
            if (null !== $item->getVariantId()) {
                $variant = $this->productVariantRepository->find($item->getVariantId());
            }

            $quantity = $item->getQuantity();
            $this->guardStockLevel($product, $variant, $quantity);

            if ($variant) {
                $variant->setStock(max(0, $variant->getStock() - $quantity));
            } else {
                $product->setStock(max(0, $product->getStock() - $quantity));
            }
        }
    }
}
