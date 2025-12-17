<?php

namespace App\Controller\Web;

use App\Entity\CustomerOrder;
use App\Repository\CustomerOrderRepository;
use App\Service\CheckoutService;
use App\Service\StripePaymentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripePaymentService $stripePaymentService,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly CheckoutService $checkoutService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('stripe-signature', '');
        $secret = $this->stripePaymentService->getWebhookSecret();

        if (!$secret) {
            $this->logger->warning('Stripe webhook secret missing');

            return new Response('Webhook disabled', Response::HTTP_OK);
        }

        if (!$this->isValidSignature($payload, $signature, $secret)) {
            $this->logger->warning('Stripe webhook invalid signature');

            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        if ($type === 'checkout.session.completed') {
            $this->handleSessionCompleted($data);
        } elseif ($type === 'checkout.session.expired') {
            $this->handleSessionExpired($data);
        }

        return new Response('ok', Response::HTTP_OK);
    }

    private function handleSessionCompleted(array $session): void
    {
        $sessionId = $session['id'] ?? null;
        $reference = $session['metadata']['order_reference'] ?? null;
        $paymentIntent = $session['payment_intent'] ?? null;

        $order = null;
        if ($sessionId) {
            $order = $this->orderRepository->findOneBy(['paymentSessionId' => $sessionId]);
        }
        if (!$order && $reference) {
            $order = $this->orderRepository->findOneBy(['reference' => $reference]);
        }

        if (!$order) {
            $this->logger->warning('Stripe webhook: order not found', ['session' => $sessionId, 'reference' => $reference]);

            return;
        }

        if ($order->getStatus() === CustomerOrder::STATUS_PAID) {
            return;
        }

        $order->setPaymentSessionId($sessionId);
        $this->checkoutService->finalizePayment(
            $order,
            is_string($paymentIntent) ? $paymentIntent : null,
            ['triggered_by' => 'stripe:webhook']
        );
    }

    private function handleSessionExpired(array $session): void
    {
        $sessionId = $session['id'] ?? null;
        if (!$sessionId) {
            return;
        }

        $order = $this->orderRepository->findOneBy(['paymentSessionId' => $sessionId]);
        if (!$order) {
            return;
        }

        if ($order->getStatus() === CustomerOrder::STATUS_PAID) {
            return;
        }

        $this->checkoutService->cancelOrder($order, ['triggered_by' => 'stripe:webhook']);
    }

    private function isValidSignature(string $payload, string $header, string $secret): bool
    {
        if (!$header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[$key] = $value;
            }
        }

        if (!isset($parts['t'], $parts['v1'])) {
            return false;
        }

        $signedPayload = sprintf('%s.%s', $parts['t'], $payload);
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $parts['v1']);
    }
}
