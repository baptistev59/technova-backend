<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\CustomerOrder;
use App\Entity\CustomerOrderItem;
use App\Entity\Shop;
use App\Entity\Product;
use App\Repository\VatRateRepository;
use App\Service\VatCalculator;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager as PersistenceObjectManager;

final class OrderVatSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly VatCalculator $vatCalculator,
        private readonly VatRateRepository $vatRateRepository,
        private readonly ManagerRegistry $managerRegistry
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof CustomerOrder) {
                continue;
            }

            $this->processOrder($entity, $em, $uow);
        }
    }

    private function processOrder(CustomerOrder $order, PersistenceObjectManager $em, $uow): void
    {
        $shipping = $order->getShippingAddress();
        $country = $shipping['country'] ?? $order->getBillingAddress()['country'] ?? null;
        if (null === $country) {
            return; // no country, cannot compute VAT
        }

        $itemsNetTotal = 0.0;
        $itemsVatTotal = 0.0;
        $itemsGrossTotal = 0.0;

        foreach ($order->getItems() as $item) {
            if (!$item instanceof CustomerOrderItem) {
                continue;
            }

            $shop = null;
            if (null !== $item->getShopId()) {
                $shop = $em->getRepository(Shop::class)->find($item->getShopId());
            }

            // determine product tax class with fallbacks:
            // 1) tax class directly on item (if present), 2) product.taxClass, 3) 'STANDARD'
            $product = $em->getRepository(Product::class)->find($item->getProductId());
            $taxCode = 'STANDARD';
            if (method_exists($item, 'getTaxClass') && null !== $item->getTaxClass() && '' !== $item->getTaxClass()) {
                $taxCode = $item->getTaxClass();
            } elseif (null !== $product) {
                $taxCode = $product->getTaxClass();
            }

            // determine rate entity for traceability using tax class
            $rateEntity = $this->vatRateRepository->findEffectiveRate($country, $shop, $taxCode);
            $percent = null !== $rateEntity ? $rateEntity->getRate() : $this->vatCalculator->getRatePercent($country, $shop, $taxCode);

            $unitPrice = (float) $item->getUnitPrice(); // assumed net per product model
            $quantity = $item->getQuantity();

            $appliedNet = round($unitPrice * $quantity, 2);
            $appliedVatAmount = $this->vatCalculator->calculateTaxFromNet($appliedNet, $country, $shop, $taxCode);
            $appliedGross = round($appliedNet + $appliedVatAmount, 2);

            $item->setAppliedVatPercent(number_format($percent, 2, '.', ''));
            $item->setAppliedVatAmount(number_format($appliedVatAmount, 2, '.', ''));
            $item->setAppliedNetAmount(number_format($appliedNet, 2, '.', ''));
            $item->setAppliedGrossAmount(number_format($appliedGross, 2, '.', ''));
            $item->setVatCountryCode($country);
            $item->setAppliedVatId(null !== $rateEntity ? $rateEntity->getId() : null);

            $meta = $em->getClassMetadata(get_class($item));
            $uow->recomputeSingleEntityChangeSet($meta, $item);
            // accumulate totals (numbers as floats, format later)
            $itemsNetTotal += (float) $item->getAppliedNetAmount();
            $itemsVatTotal += (float) $item->getAppliedVatAmount();
            $itemsGrossTotal += (float) $item->getAppliedGrossAmount();
        }

        // set aggregated totals on order (round and format)
        $itemsNetTotal = (float) round($itemsNetTotal, 2);
        $itemsVatTotal = (float) round($itemsVatTotal, 2);
        $itemsGrossTotal = (float) round($itemsGrossTotal, 2);

        $order->setItemsNetTotal(number_format($itemsNetTotal, 2, '.', ''));
        $order->setItemsVatTotal(number_format($itemsVatTotal, 2, '.', ''));
        $order->setItemsGrossTotal(number_format($itemsGrossTotal, 2, '.', ''));

        // compute totalAmount = itemsGrossTotal + shippingTotal (simple strategy)
        $shippingTotal = (float) $order->getShippingTotal();
        $totalAmount = (float) round($itemsGrossTotal + $shippingTotal, 2);
        $order->setTotalAmount(number_format($totalAmount, 2, '.', ''));

        $orderMeta = $em->getClassMetadata(get_class($order));
        $uow->recomputeSingleEntityChangeSet($orderMeta, $order);
    }
}
