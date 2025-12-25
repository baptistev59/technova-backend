<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderDocument;
use App\Enum\DocumentType;
use App\Repository\OrderDocumentRepository;
use App\Service\OrderDocumentGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

class OrderMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $params,
        private readonly OrderDocumentGenerator $documentGenerator,
        private readonly OrderDocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.email')]
        private readonly LoggerInterface $emailLogger,
        private readonly ?string $mailerFrom = null
    ) {
    }

    public function sendConfirmation(CustomerOrder $order): void
    {
        $owner = $order->getOwner();
        $email = $owner?->getEmail();

        if (!$owner || !$email) {
            return;
        }

        $fromAddress = $this->mailerFrom
            ? Address::create($this->mailerFrom)
            : new Address('no-reply@technova.local', 'TechNova');

        $baseUrl = $this->resolveBaseUrl();

        $publicDir = rtrim((string) $this->params->get('kernel.project_dir'), '/') . '/public';

        $items = [];
        foreach ($order->getItems() as $item) {
            $image = $item->getProductImage();
            $imageUrl = null;
            $embedPath = null;

            if ($image) {
                if (str_starts_with($image, 'http')) {
                    $imageUrl = $image;
                } else {
                    $relative = ltrim(parse_url($image, PHP_URL_PATH) ?? $image, '/');
                    $fullPath = $publicDir . '/' . $relative;

                    if (is_file($fullPath)) {
                        $embedPath = $fullPath;
                    }

                    $imageUrl = sprintf('%s/%s', $baseUrl, $relative);
                }
            }

            $items[] = [
                'name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'lineTotal' => $item->getLineTotal(),
                'imageCid' => null,
                'imageUrl' => $imageUrl,
                '_embedPath' => $embedPath,
            ];
        }

        $emailMessage = (new TemplatedEmail())
            ->from($fromAddress)
            ->to($email)
            ->subject(sprintf('TechNova — Confirmation de commande %s', $order->getReference()))
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->textTemplate('emails/order_confirmation.text.twig');

        foreach ($items as $index => $itemData) {
            if (!empty($itemData['_embedPath'])) {
                $inlinePart = DataPart::fromPath($itemData['_embedPath'])->asInline();
                $emailMessage->addPart($inlinePart);
                $items[$index]['imageCid'] = 'cid:' . $inlinePart->getContentId();
            }

            unset($items[$index]['_embedPath']);
        }

        $emailMessage->context([
            'order' => $order,
            'items' => $items,
            'address' => $order->getShippingAddress(),
            'total' => $order->getTotalAmount(),
            'baseUrl' => $baseUrl,
        ]);

        $document = $this->ensureInvoiceDocument($order, $baseUrl);
        $absolute = rtrim((string) $this->params->get('kernel.project_dir'), '/') . '/public/' . ltrim($document->getPath(), '/');
        if (is_file($absolute)) {
            $emailMessage->attachFromPath($absolute, sprintf('facture-%s.pdf', $order->getReference()), 'application/pdf');
        }

        $this->mailer->send($emailMessage);
        $this->emailLogger->info('Order confirmation email sent', [
            'order' => $order->getReference(),
            'email' => $email,
        ]);
    }

    public function sendStatusUpdate(CustomerOrder $order, string $transition): void
    {
        $owner = $order->getOwner();
        $email = $owner?->getEmail();
        if (!$owner || !$email) {
            return;
        }

        $labels = [
            'pay' => 'paiement confirmé',
            'ship' => 'commande expédiée',
            'cancel' => 'commande annulée',
        ];

        $subjectLabel = $labels[$transition] ?? 'mise à jour';

        $fromAddress = $this->mailerFrom
            ? Address::create($this->mailerFrom)
            : new Address('no-reply@technova.local', 'TechNova');

        $baseUrl = $this->resolveBaseUrl();

        $emailMessage = (new TemplatedEmail())
            ->from($fromAddress)
            ->to($email)
            ->subject(sprintf('TechNova — %s (%s)', ucfirst($subjectLabel), $order->getReference()))
            ->htmlTemplate('emails/order_status_update.html.twig')
            ->textTemplate('emails/order_status_update.text.twig')
            ->context([
                'order' => $order,
                'transition' => $transition,
                'status' => $order->getStatus(),
                'subject_label' => $subjectLabel,
                'baseUrl' => $baseUrl,
            ]);

        $document = $this->ensureInvoiceDocument($order);
        $absolute = rtrim((string) $this->params->get('kernel.project_dir'), '/') . '/public/' . ltrim($document->getPath(), '/');
        if (is_file($absolute)) {
            $emailMessage->attachFromPath($absolute, sprintf('facture-%s.pdf', $order->getReference()), 'application/pdf');
        }

        $document = $this->ensureInvoiceDocument($order, $baseUrl);
        $absolute = rtrim((string) $this->params->get('kernel.project_dir'), '/') . '/public/' . ltrim($document->getPath(), '/');
        if (is_file($absolute)) {
            $emailMessage->attachFromPath($absolute, sprintf('facture-%s.pdf', $order->getReference()), 'application/pdf');
        }

        $this->mailer->send($emailMessage);
        $this->emailLogger->info('Order status email sent', [
            'order' => $order->getReference(),
            'email' => $email,
            'transition' => $transition,
        ]);
    }

    private function ensureInvoiceDocument(CustomerOrder $order, string $baseUrl): OrderDocument
    {
        $existing = $this->documentRepository->findOneBy([
            'order' => $order,
            'type' => DocumentType::INVOICE->value,
        ]);
        if ($existing instanceof OrderDocument) {
            return $existing;
        }

        $document = $this->documentGenerator->generate($order, DocumentType::INVOICE, $baseUrl);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    private function resolveBaseUrl(): string
    {
        $base = $this->params->has('router.default_uri') ? (string) $this->params->get('router.default_uri') : '';
        if ($base !== '') {
            return rtrim($base, '/');
        }

        return rtrim($_ENV['DEFAULT_URI'] ?? 'https://technova.local', '/');
    }
}
