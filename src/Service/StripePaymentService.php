<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripePaymentService
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $publishableKey,
        private readonly ?string $webhookSecret = null,
        private readonly HttpClientInterface $httpClient,
        private readonly string $defaultBaseUrl,
        private readonly LoggerInterface $logger
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
        if ($this->secretKey === '') {
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

        try {
            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
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

    private function convertAmountToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function absoluteImageUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($this->defaultBaseUrl, '/') . '/' . ltrim($path, '/');
    }
}
