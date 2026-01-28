<?php

namespace App\Tests\Double;

use App\Service\StripePaymentService;
use App\Entity\Order;

/**
 * Fake Stripe service for functional tests to avoid external HTTP calls.
 */
class FakeStripePaymentService extends StripePaymentService
{
    public function __construct()
    {
        // Intentionally empty: parent dependencies (HTTP client, keys) not needed for the fake.
    }

    /**
     * Return a predictable session payload for tests.
     */
    public function createCheckoutSession(array $products, Order $order): array
    {
        return [
            'id' => 'cs_test_fake',
            'url' => 'https://stripe.test/session/cs_test_fake',
        ];
    }

    /**
     * Always succeed refunds in tests.
     */
    public function refundPayment(string $paymentIntentId): array
    {
        return [
            'id' => 're_test_fake',
            'status' => 'succeeded',
        ];
    }
}
