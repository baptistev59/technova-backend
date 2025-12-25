<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminVendorActionController extends AbstractController
{
    #[Route('/admin/vendors/{id}/suspend', name: 'admin_vendor_suspend', methods: ['POST'])]
    public function suspend(Vendor $vendor, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->isCsrfTokenValidOrThrow('suspend_vendor_' . $vendor->getId(), $request->request->get('_token'));

        $vendor->setIsSuspended(true);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Le vendeur %s a été suspendu.', $vendor->getCompanyName()));

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }

    #[Route('/admin/vendors/{id}/activate', name: 'admin_vendor_activate', methods: ['POST'])]
    public function activate(Vendor $vendor, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->isCsrfTokenValidOrThrow('activate_vendor_' . $vendor->getId(), $request->request->get('_token'));

        $vendor->setIsSuspended(false);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Le vendeur %s a été réactivé.', $vendor->getCompanyName()));

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }

    private function isCsrfTokenValidOrThrow(string $id, ?string $token): void
    {
        if (!$this->isCsrfTokenValid($id, $token ?? '')) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
