<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CustomerOrder;
use App\Enum\AuditAction;
use App\Enum\OrderStatus;
use App\Service\AuditLoggerService;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminOrderActionController extends AbstractController
{
    public function __construct(
        private readonly AuditLoggerService $auditLogger,
        private readonly StripePaymentService $stripePaymentService,
    ) {
    }

    #[Route('/admin/orders/{id}/block', name: 'admin_order_block', methods: ['POST'])]
    public function blockOrder(
        CustomerOrder $order,
        Request $request,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->isCsrfTokenValidOrThrow('block_order_'.$order->getId(), $request->request->get('_token'));

        $previousStatus = $order->getStatus();
        $order->setStatus(OrderStatus::Cancelled);
        $entityManager->flush();
        $this->auditLogger->log(
            action: AuditAction::AdminOrderBlock,
            resource: 'customer_order',
            resourceId: $order->getId(),
            data: [
                'reference' => $order->getReference(),
                'from' => $previousStatus,
                'to' => OrderStatus::Cancelled->value,
            ]
        );

        $this->addFlash('success', sprintf('La commande %s a été bloquée.', $order->getReference()));

        $redirect = $request->headers->get('referer') ?? $this->generateUrl('admin_dashboard');

        return $this->redirect($redirect);
    }

    #[Route('/admin/orders/{id}/force-status', name: 'admin_order_force_status', methods: ['POST'])]
    public function forceStatus(
        CustomerOrder $order,
        Request $request,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->isCsrfTokenValidOrThrow('force_status_'.$order->getId(), $request->request->get('_token'));

        $newStatus = (string) $request->request->get('new_status');
        $allowed = array_map(
            static fn (OrderStatus $status): string => $status->value,
            OrderStatus::cases()
        );

        if (!in_array($newStatus, $allowed, true)) {
            $this->addFlash('danger', 'Statut invalide.');
        } else {
            $previousStatus = $order->getStatus();
            $order->setStatus($newStatus);
            $entityManager->flush();
            $this->auditLogger->log(
                action: AuditAction::AdminOrderForceStatus,
                resource: 'customer_order',
                resourceId: $order->getId(),
                data: [
                    'reference' => $order->getReference(),
                    'from' => $previousStatus,
                    'to' => $newStatus,
                ]
            );
            $this->addFlash('success', sprintf('La commande %s est maintenant "%s".', $order->getReference(), $newStatus));
        }

        $redirect = $request->headers->get('referer') ?? $this->generateUrl('admin_dashboard');

        return $this->redirect($redirect);
    }

    #[Route('/admin/orders/{id}/refund', name: 'admin_order_refund', methods: ['POST'])]
    public function refund(
        CustomerOrder $order,
        Request $request,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->isCsrfTokenValidOrThrow('refund_order_'.$order->getId(), $request->request->get('_token'));

        if ($order->getRefundedAt()) {
            $this->addFlash('warning', 'Commande déjà remboursée.');

            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
        }

        $status = $order->getStatusEnum();
        $isEligible = match ($status) {
            OrderStatus::Paid, OrderStatus::Shipped => true,
            default => false,
        };
        if (!$isEligible) {
            $this->addFlash('danger', 'Commande non éligible au remboursement.');

            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
        }

        $amountCents = null;
        $amountRaw = trim((string) $request->request->get('refund_amount', ''));
        if ('' !== $amountRaw) {
            $normalized = str_replace(',', '.', $amountRaw);
            if (!is_numeric($normalized)) {
                $this->addFlash('danger', 'Montant de remboursement invalide.');

                return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
            }

            $amount = (float) $normalized;
            if ($amount <= 0) {
                $this->addFlash('danger', 'Le montant doit être supérieur à 0.');

                return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
            }

            $totalCents = (int) round(((float) $order->getTotalAmount()) * 100);
            $amountCents = (int) round($amount * 100);
            if ($amountCents > $totalCents) {
                $this->addFlash('danger', 'Le montant dépasse le total de la commande.');

                return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
            }
        }

        try {
            $refund = $this->stripePaymentService->refundPayment($order, $amountCents);
        } catch (\RuntimeException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
        }

        $order
            ->setRefundId($refund['id'])
            ->setRefundedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->auditLogger->log(
            action: AuditAction::AdminOrderRefund,
            resource: 'customer_order',
            resourceId: $order->getId(),
            data: [
                'reference' => $order->getReference(),
                'refund_id' => $refund['id'],
                'status' => $refund['status'],
                'amount_cents' => $amountCents,
            ]
        );

        $this->addFlash('success', sprintf('Commande %s remboursée.', $order->getReference()));

        $redirect = $request->headers->get('referer') ?? $this->generateUrl('admin_dashboard');

        return $this->redirect($redirect);
    }

    private function isCsrfTokenValidOrThrow(string $id, ?string $token): void
    {
        if (!$this->isCsrfTokenValid($id, $token ?? '')) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
