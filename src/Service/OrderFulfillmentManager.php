<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\CustomerOrderItem;
use App\Entity\Shop;
use App\Enum\OrderItemFulfillmentStatus;
use App\Enum\OrderStatus;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class OrderFulfillmentManager
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function markShopItemsAsShipped(Shop $shop, CustomerOrder $order, ?\DateTimeImmutable $when = null): bool
    {
        $items = $this->getShopItems($shop, $order);
        if ([] === $items) {
            return false;
        }

        foreach ($items as $item) {
            $item->markFulfilled($when);
            $this->entityManager->persist($item);
        }

        $orderWentShipped = $this->refreshOrderStatus($order);
        $this->entityManager->flush();

        return $orderWentShipped;
    }

    public function markItemAsPending(CustomerOrderItem $item): bool
    {
        if (OrderItemFulfillmentStatus::Pending === $item->getFulfillmentStatus()) {
            return false;
        }

        $item->setFulfillmentStatus(OrderItemFulfillmentStatus::Pending);
        $item->setFulfilledAt(null);
        $this->entityManager->persist($item);

        $orderWentShipped = $this->refreshOrderStatus($item->getCustomerOrder());
        $this->entityManager->flush();

        return $orderWentShipped;
    }

    public function markItemAsCancelled(CustomerOrderItem $item): bool
    {
        if (OrderItemFulfillmentStatus::Cancelled === $item->getFulfillmentStatus()) {
            return false;
        }

        $item->setFulfillmentStatus(OrderItemFulfillmentStatus::Cancelled);
        $item->setFulfilledAt(null);
        $this->entityManager->persist($item);

        $orderWentShipped = $this->refreshOrderStatus($item->getCustomerOrder());
        $this->entityManager->flush();

        return $orderWentShipped;
    }

    /**
     * @return list<CustomerOrderItem>
     */
    public function getShopItems(Shop $shop, CustomerOrder $order, bool $onlyFulfilled = false): array
    {
        $productMap = $this->mapShopProductIds($shop, $order);
        if ([] === $productMap) {
            return [];
        }

        $items = array_values(array_filter(
            $order->getItems()->toArray(),
            static fn (CustomerOrderItem $item): bool => null !== $item->getProductId()
                && isset($productMap[(int) $item->getProductId()])
        ));

        if ($onlyFulfilled) {
            $items = array_filter($items, static fn (CustomerOrderItem $item): bool => $item->isFulfilled());
        }

        return array_values($items);
    }

    public function describeShopStatus(Shop $shop, CustomerOrder $order): array
    {
        $items = $this->getShopItems($shop, $order);
        $total = count($items);
        if (0 === $total) {
            return [
                'status' => 'pending',
                'label' => 'Pas d’article',
                'badge_class' => 'bg-slate-100 text-slate-500 border-slate-200',
                'shipped_items' => 0,
                'total_items' => 0,
            ];
        }

        $shipped = 0;
        foreach ($items as $item) {
            if ($item->isFulfilled()) {
                ++$shipped;
            }
        }

        $status = match (true) {
            $shipped === $total => 'shipped',
            $shipped > 0 => 'partial',
            default => 'pending',
        };

        $labels = [
            'shipped' => 'Expédié',
            'partial' => 'Partiellement expédié',
            'pending' => 'En attente',
        ];
        $classes = [
            'shipped' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            'partial' => 'bg-amber-50 text-amber-700 border border-amber-200',
            'pending' => 'bg-slate-100 text-slate-500 border border-slate-200',
        ];

        return [
            'status' => $status,
            'label' => $labels[$status],
            'badge_class' => $classes[$status],
            'shipped_items' => $shipped,
            'total_items' => $total,
        ];
    }

    private function mapShopProductIds(Shop $shop, CustomerOrder $order): array
    {
        $productIds = [];
        foreach ($order->getItems() as $item) {
            $productIds[] = $item->getProductId();
        }

        $productIds = array_values(array_unique(array_filter($productIds, static fn ($id) => is_int($id) || ctype_digit((string) $id))));
        if ([] === $productIds) {
            return [];
        }

        $rows = $this->productRepository->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.shop = :shop')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('shop', $shop)
            ->setParameter('ids', $productIds)
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = true;
        }

        return $map;
    }

    private function refreshOrderStatus(CustomerOrder $order): bool
    {
        if (OrderStatus::Cancelled === $order->getStatusEnum()) {
            return false;
        }

        $allClosed = true;
        foreach ($order->getItems() as $item) {
            if (!$item->getFulfillmentStatus()->isClosed()) {
                $allClosed = false;
                break;
            }
        }

        if ($allClosed) {
            if (OrderStatus::Shipped === $order->getStatusEnum()) {
                return false;
            }

            $order->setStatus(OrderStatus::Shipped);

            return true;
        }

        if (OrderStatus::Shipped === $order->getStatusEnum()) {
            $order->setStatus(OrderStatus::Paid);

            return true;
        }

        return false;
    }
}
