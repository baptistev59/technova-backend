<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OrderStatusNotifier
{
    public function __construct(
        private readonly OrderMailer $orderMailer,
        #[Autowire(service: 'monolog.logger.order')]
        private readonly LoggerInterface $logger,
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
