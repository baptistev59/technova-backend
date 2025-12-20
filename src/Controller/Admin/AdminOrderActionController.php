<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CustomerOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminOrderActionController extends AbstractController
{
    #[Route('/admin/orders/{id}/block', name: 'admin_order_block', methods: ['POST'])]
    public function blockOrder(
        CustomerOrder $order,
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $this->isCsrfTokenValidOrThrow('block_order_' . $order->getId(), $request->request->get('_token'));

        $order->setStatus(CustomerOrder::STATUS_CANCELLED);
        $entityManager->flush();

        $this->addFlash('success', sprintf('La commande %s a été bloquée.', $order->getReference()));

        $redirect = $request->headers->get('referer') ?? $this->generateUrl('admin_dashboard');

        return $this->redirect($redirect);
    }

    #[Route('/admin/orders/{id}/force-status', name: 'admin_order_force_status', methods: ['POST'])]
    public function forceStatus(
        CustomerOrder $order,
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $this->isCsrfTokenValidOrThrow('force_status_' . $order->getId(), $request->request->get('_token'));

        $newStatus = (string) $request->request->get('new_status');
        $allowed = [
            CustomerOrder::STATUS_PENDING,
            CustomerOrder::STATUS_PAID,
            CustomerOrder::STATUS_SHIPPED,
            CustomerOrder::STATUS_CANCELLED,
        ];

        if (!in_array($newStatus, $allowed, true)) {
            $this->addFlash('danger', 'Statut invalide.');
        } else {
            $order->setStatus($newStatus);
            $entityManager->flush();
            $this->addFlash('success', sprintf('La commande %s est maintenant "%s".', $order->getReference(), $newStatus));
        }

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
