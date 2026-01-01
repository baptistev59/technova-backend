<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripePaymentService
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $publishableKey,
        private readonly HttpClientInterface $httpClient,
        private readonly string $defaultBaseUrl,
        #[Autowire(service: 'monolog.logger.payment')]
        private readonly LoggerInterface $logger,
        private readonly ?string $webhookSecret = null,
    ) {
    }

    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    /**
     * @return array{ id: string, url: string }
     */
    public function createCheckoutSession(CustomerOrder $order, string $successUrl, string $cancelUrl): array
    {
        if ('' === $this->secretKey) {
            throw new \RuntimeException('Stripe n\'est pas configuré sur cet environnement.');
        }

        $body = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $order->getOwner()?->getEmail(),
            'metadata[order_reference]' => $order->getReference(),
        ];

        $currency = strtolower($order->getCurrency());
        $index = 0;
        foreach ($order->getItems() as $item) {
            $body[sprintf('line_items[%d][price_data][currency]', $index)] = $currency;
            $body[sprintf('line_items[%d][price_data][product_data][name]', $index)] = mb_substr($item->getProductName(), 0, 120);
            if ($item->getProductImage()) {
                $body[sprintf('line_items[%d][price_data][product_data][images][]', $index)] = $this->absoluteImageUrl($item->getProductImage());
            }
            $body[sprintf('line_items[%d][price_data][unit_amount]', $index)] = $this->convertAmountToCents($item->getUnitPrice());
            $body[sprintf('line_items[%d][quantity]', $index)] = $item->getQuantity();
            ++$index;
        }

        foreach ($order->getShippingLines() ?? [] as $shippingLine) {
            if (!isset($shippingLine['price'])) {
                continue;
            }
            $label = sprintf('Livraison - %s', $shippingLine['shopName'] ?? 'Boutique');
            if (!empty($shippingLine['methodName'])) {
                $label .= sprintf(' (%s)', $shippingLine['methodName']);
            }
            $body[sprintf('line_items[%d][price_data][currency]', $index)] = $currency;
            $body[sprintf('line_items[%d][price_data][product_data][name]', $index)] = mb_substr($label, 0, 120);
            $body[sprintf('line_items[%d][price_data][unit_amount]', $index)] = $this->convertAmountToCents((string) $shippingLine['price']);
            $body[sprintf('line_items[%d][quantity]', $index)] = 1;
            ++$index;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ]);

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Stripe session creation failed', ['error' => $exception->getMessage()]);
            throw new \RuntimeException('Impossible de contacter Stripe. Réessaie dans quelques instants.');
        }

        if (empty($data['id']) || empty($data['url'])) {
            $this->logger->error('Stripe session missing data', ['response' => $data]);
            throw new \RuntimeException('Réponse Stripe inattendue, paiement indisponible.');
        }

        return [
            'id' => $data['id'],
            'url' => $data['url'],
        ];
    }

    /**
     * @return array{id: string, status: string}
     */
    public function refundPayment(CustomerOrder $order, ?int $amountCents = null): array
    {
        if ('' === $this->secretKey) {
            throw new \RuntimeException('Stripe n\'est pas configuré sur cet environnement.');
        }

        $paymentIntentId = $order->getPaymentIntentId();
        if (!$paymentIntentId) {
            throw new \RuntimeException('Aucun paiement Stripe associé à cette commande.');
        }

        $body = [
            'payment_intent' => $paymentIntentId,
        ];
        if (null !== $amountCents) {
            $body['amount'] = $amountCents;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/refunds', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->secretKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ]);

            $data = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Stripe refund failed', ['error' => $exception->getMessage()]);
            throw new \RuntimeException('Impossible de contacter Stripe pour le remboursement.');
        }

        if (empty($data['id']) || empty($data['status'])) {
            $this->logger->error('Stripe refund missing data', ['response' => $data]);
            throw new \RuntimeException('Réponse Stripe inattendue lors du remboursement.');
        }

        return [
            'id' => (string) $data['id'],
            'status' => (string) $data['status'],
        ];
    }

    private function convertAmountToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function absoluteImageUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($this->defaultBaseUrl, '/').'/'.ltrim($path, '/');
    }
}
