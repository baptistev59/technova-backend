<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Conversation;
use App\Entity\CustomerOrder;
use App\Entity\Message;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Service\ConversationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
final class ConversationController extends AbstractController
{
    public function __construct(
        private readonly CustomerOrderRepository $orderRepository,
        private readonly ConversationManager $conversationManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/vendor/conversations/{orderId}', name: 'vendor_conversation_get', methods: ['GET'])]
    public function vendorConversation(int $orderId): JsonResponse
    {
        $conversation = $this->resolveVendorConversation($orderId);

        return $this->json($this->serializeConversation($conversation));
    }

    #[Route('/vendor/conversations/{orderId}/messages', name: 'vendor_conversation_message', methods: ['POST'])]
    public function vendorPostMessage(int $orderId, Request $request): JsonResponse
    {
        $conversation = $this->resolveVendorConversation($orderId);

        return $this->handleMessagePost($conversation, $request);
    }

    #[Route('/account/conversations/{orderId}', name: 'account_conversation_get', methods: ['GET'])]
    public function accountConversation(int $orderId): JsonResponse
    {
        $conversation = $this->resolveAccountConversation($orderId);

        return $this->json($this->serializeConversation($conversation));
    }

    #[Route('/account/conversations/{orderId}/messages', name: 'account_conversation_message', methods: ['POST'])]
    public function accountPostMessage(int $orderId, Request $request): JsonResponse
    {
        $conversation = $this->resolveAccountConversation($orderId);

        return $this->handleMessagePost($conversation, $request);
    }

    private function resolveVendorConversation(int $orderId): Conversation
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getVendor()) {
            throw $this->createAccessDeniedException('Accès vendeur requis.');
        }

        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            throw $this->createNotFoundException();
        }

        $vendorShopIds = array_map(
            static fn (Shop $shop): int => $shop->getId(),
            $user->getVendor()->getShops()->toArray()
        );

        $allowed = false;
        foreach ($order->getItems() as $item) {
            if (null !== $item->getShopId() && in_array($item->getShopId(), $vendorShopIds, true)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw $this->createNotFoundException();
        }

        return $this->conversationManager->getOrCreate($order);
    }

    private function resolveAccountConversation(int $orderId): Conversation
    {
        /** @var CustomerOrder|null $order */
        $order = $this->orderRepository->find($orderId);
        $user = $this->getUser();
        if (!$user instanceof User || !$order || $order->getOwner()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->conversationManager->getOrCreate($order);
    }

    private function handleMessagePost(Conversation $conversation, Request $request): JsonResponse
    {
        $content = trim((string) $request->request->get('content', ''));
        if ('' === $content) {
            return $this->json(['error' => 'Le message ne peut être vide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentification requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $message = (new Message())
            ->setConversation($conversation)
            ->setAuthor($user)
            ->setContent($content);

        $conversation->addMessage($message);
        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $this->json($this->serializeMessage($message), JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConversation(Conversation $conversation): array
    {
        return [
            'orderId' => $conversation->getOrder()->getId(),
            'shopId' => $conversation->getShop()->getId(),
            'messages' => array_map(fn (Message $message) => $this->serializeMessage($message), $conversation->getMessages()->toArray()),
        ];
    }

    private function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->getId(),
            'authorId' => $message->getAuthor()->getId(),
            'authorName' => trim($message->getAuthor()->getFirstname().' '.$message->getAuthor()->getLastname()),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()?->format(\DateTimeImmutable::ATOM),
        ];
    }
}
