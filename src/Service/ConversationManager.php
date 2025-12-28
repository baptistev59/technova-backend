<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\CustomerOrder;
use App\Entity\Shop;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ConversationManager
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreate(CustomerOrder $order): Conversation
    {
        $conversation = $this->conversationRepository->findByOrderId($order->getId());
        if ($conversation) {
            return $conversation;
        }

        $conversation = (new Conversation())->setOrder($order, $this->resolveShopForOrder($order));
        $this->entityManager->persist($conversation);

        return $conversation;
    }

    private function resolveShopForOrder(CustomerOrder $order): Shop
    {
        foreach ($order->getItems() as $item) {
            if ($item->getShopId()) {
                return $this->entityManager->getReference(Shop::class, $item->getShopId());
            }
        }

        throw new \RuntimeException('Impossible de déterminer la boutique pour la commande.');
    }
}
