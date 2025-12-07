<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\CustomerOrder;
use App\Entity\CustomerOrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Convertit un panier en commande persistée.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderMailer $orderMailer
    ) {
    }

    public function createOrder(User $user, Address $shippingAddress): CustomerOrder
    {
        $summary = $this->cartService->getSummary();
        if (empty($summary['items'])) {
            throw new \RuntimeException('Panier vide, impossible de créer la commande.');
        }

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
            $item = (new CustomerOrderItem())
                ->setProductId($product->getId())
                ->setProductName($product->getName())
                ->setProductImage($this->resolveProductImage($product))
                ->setQuantity($cartLine['quantity'])
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

    public function finalizePayment(CustomerOrder $order, ?string $paymentIntentId = null): void
    {
        $order
            ->setStatus(CustomerOrder::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable())
            ->setPaymentIntentId($paymentIntentId);

        $this->entityManager->flush();
        $this->orderMailer->sendConfirmation($order);
    }

    public function cancelOrder(CustomerOrder $order): void
    {
        $order->setStatus(CustomerOrder::STATUS_CANCELLED);
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
}
