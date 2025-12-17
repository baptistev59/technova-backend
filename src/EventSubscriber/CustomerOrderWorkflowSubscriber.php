<?php

namespace App\EventSubscriber;

use App\Entity\CustomerOrder;
use App\Entity\CustomerOrderStatusHistory;
use App\Service\OrderStatusNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\WorkflowEvents;

class CustomerOrderWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderStatusNotifier $notifier
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkflowEvents::COMPLETED . '.customer_order' => 'onCompleted',
        ];
    }

    public function onCompleted(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof CustomerOrder) {
            return;
        }

        $transition = $event->getTransition()->getName();
        $from = $event->getTransition()->getFroms()[0] ?? $order->getStatus();

        $history = (new CustomerOrderStatusHistory())
            ->setOrder($order)
            ->setFromStatus($from)
            ->setToStatus($order->getStatus())
            ->setTransition($transition)
            ->setChangedAt(new \DateTimeImmutable())
            ->setTriggeredBy($event->getContext()['triggered_by'] ?? null)
            ->setPayload($event->getContext()['payload'] ?? null);

        $this->entityManager->persist($history);
        $this->notifier->notify($order, $transition);
    }
}
