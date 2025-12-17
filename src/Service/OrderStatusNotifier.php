<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use Psr\Log\LoggerInterface;

class OrderStatusNotifier
{
    public function __construct(
        private readonly OrderMailer $orderMailer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function notify(CustomerOrder $order, string $transition): void
    {
        try {
            $this->orderMailer->sendStatusUpdate($order, $transition);
        } catch (\Throwable $exception) {
            $this->logger->error('Order status notification failed', [
                'order' => $order->getReference(),
                'transition' => $transition,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->logger->info('Order status transition', [
            'order' => $order->getReference(),
            'status' => $order->getStatus(),
            'transition' => $transition,
        ]);
    }
}
