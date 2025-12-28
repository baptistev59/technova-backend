<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminProductActionController extends AbstractController
{
    public function __construct(
        private readonly AuditLoggerService $auditLogger,
    ) {
    }

    #[Route('/admin/products/{id}/hide', name: 'admin_product_hide', methods: ['POST'])]
    public function hide(Product $product, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->isCsrfTokenValidOrThrow('hide_product_'.$product->getId(), $request->request->get('_token'));

        $product->setIsPublished(false);
        $entityManager->flush();
        $this->auditLogger->log(
            action: AuditAction::AdminProductHide,
            resource: 'product',
            resourceId: $product->getId(),
            data: [
                'name' => $product->getName(),
            ]
        );

        $this->addFlash('success', sprintf('Le produit %s est maintenant masqué.', $product->getName()));

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }

    #[Route('/admin/products/{id}/publish', name: 'admin_product_publish', methods: ['POST'])]
    public function publish(Product $product, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->isCsrfTokenValidOrThrow('publish_product_'.$product->getId(), $request->request->get('_token'));

        $product->setIsPublished(true);
        $entityManager->flush();
        $this->auditLogger->log(
            action: AuditAction::AdminProductPublish,
            resource: 'product',
            resourceId: $product->getId(),
            data: [
                'name' => $product->getName(),
            ]
        );

        $this->addFlash('success', sprintf('Le produit %s est de nouveau visible.', $product->getName()));

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }

    private function isCsrfTokenValidOrThrow(string $id, ?string $token): void
    {
        if (!$this->isCsrfTokenValid($id, $token ?? '')) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
