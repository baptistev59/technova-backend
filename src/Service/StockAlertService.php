<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\AuditAction;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class StockAlertService
{
    private const LOW_STOCK_THRESHOLD = 10;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $params,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuditLoggerService $auditLogger,
        #[Autowire(service: 'monolog.logger.stock')]
        private readonly LoggerInterface $stockLogger,
        private readonly ?string $mailerFrom = null,
    ) {
    }

    public function getThreshold(): int
    {
        return self::LOW_STOCK_THRESHOLD;
    }

    public function resolveThreshold(Product $product, ?ProductVariant $variant): ?int
    {
        if ($product->getType() === 'grouped') {
            return null;
        }

        $variantThreshold = $variant?->getLowStockThreshold();
        if (null !== $variantThreshold) {
            return $variantThreshold;
        }

        $productThreshold = $product->getLowStockThreshold();
        if (null !== $productThreshold) {
            return $productThreshold;
        }

        return self::LOW_STOCK_THRESHOLD;
    }

    public function notifyLowStock(Product $product, ?ProductVariant $variant, int $remainingStock, int $threshold): void
    {
        $shop = $product->getShop();
        $vendor = $shop?->getOwner();
        $owner = $vendor?->getOwner();

        $recipient = $owner?->getEmail() ?: $vendor?->getEmail() ?: $shop?->getContactEmail();
        if (!$recipient) {
            return;
        }

        $fromAddress = $this->mailerFrom
            ? Address::create($this->mailerFrom)
            : new Address('no-reply@technova.local', 'TechNova');

        $productLabel = $product->getName() ?? 'Produit';
        $variantLabel = $variant ? $this->formatVariantLabel($variant) : null;
        $dashboardUrl = $this->generateVendorProductsUrl();

        $message = (new TemplatedEmail())
            ->from($fromAddress)
            ->to($recipient)
            ->subject(sprintf('TechNova — Stock faible (%s)', $productLabel))
            ->htmlTemplate('emails/stock_low.html.twig')
            ->textTemplate('emails/stock_low.text.twig')
            ->context([
                'shop' => $shop,
                'product' => $product,
                'variant_label' => $variantLabel,
                'remaining_stock' => $remainingStock,
                'threshold' => $threshold,
                'dashboard_url' => $dashboardUrl,
            ]);

        $this->mailer->send($message);
        $this->stockLogger->warning('Low stock alert sent', [
            'product_id' => $product->getId(),
            'variant_id' => $variant?->getId(),
            'remaining_stock' => $remainingStock,
        ]);

        $this->auditLogger->log(
            AuditAction::StockLow,
            resource: $variant ? 'product_variant' : 'product',
            resourceId: $variant?->getId() ?? $product->getId(),
            data: [
                'shop_id' => $shop?->getId(),
                'product_id' => $product->getId(),
                'variant_id' => $variant?->getId(),
                'remaining_stock' => $remainingStock,
                'threshold' => $threshold,
            ],
            owner: $owner instanceof User ? $owner : null,
        );
    }

    private function formatVariantLabel(ProductVariant $variant): string
    {
        $metadata = $variant->getMetadata() ?? [];
        if ($metadata !== []) {
            return implode(' / ', array_values($metadata));
        }

        return $variant->getSku() ?: 'Variante';
    }

    private function generateVendorProductsUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $this->urlGenerator->generate('app_vendor_products', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $defaultUri = $this->params->get('router.default_uri');
        if (is_string($defaultUri) && '' !== $defaultUri) {
            $path = $this->urlGenerator->generate('app_vendor_products', [], UrlGeneratorInterface::ABSOLUTE_PATH);
            return rtrim($defaultUri, '/').$path;
        }

        return $this->urlGenerator->generate('app_vendor_products', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
