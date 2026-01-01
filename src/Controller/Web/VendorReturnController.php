<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\ReturnRequest;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ReturnRequestStatus;
use App\Repository\ReturnRequestRepository;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Security\ViewerAccessChecker;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-espace-vendeur/retours')]
final class VendorReturnController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly ShopRepository $shopRepository,
        private readonly ReturnRequestRepository $returnRequestRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly StripePaymentService $stripePaymentService,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'app_vendor_returns', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $vendor = $shop->getOwner();
        $returns = $vendor ? $this->returnRequestRepository->findForVendor($vendor) : [];

        return $this->render('vendor/returns/index.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_returns'),
            'shop' => $shop,
            'returns' => $returns,
        ]);
    }

    #[Route('/{id}/valider', name: 'app_vendor_returns_approve', methods: ['POST'])]
    public function approve(ReturnRequest $returnRequest, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $vendor = $shop->getOwner();
        $allowed = $vendor ? $this->returnRequestRepository->findOneForVendor($vendor, $returnRequest->getId() ?? 0) : null;
        if (!$allowed) {
            throw $this->createNotFoundException('Demande introuvable.');
        }
        if (!$this->isCsrfTokenValid('approve_return_'.$returnRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        if (ReturnRequestStatus::Pending === $returnRequest->getStatus()) {
            $returnRequest->setStatus(ReturnRequestStatus::Approved);
            $this->entityManager->flush();
            $this->addFlash('success', 'Demande de retour validée.');
        }

        return $this->redirectToRoute('app_vendor_returns');
    }

    #[Route('/{id}/refuser', name: 'app_vendor_returns_reject', methods: ['POST'])]
    public function reject(ReturnRequest $returnRequest, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $vendor = $shop->getOwner();
        $allowed = $vendor ? $this->returnRequestRepository->findOneForVendor($vendor, $returnRequest->getId() ?? 0) : null;
        if (!$allowed) {
            throw $this->createNotFoundException('Demande introuvable.');
        }
        if (!$this->isCsrfTokenValid('reject_return_'.$returnRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        if (ReturnRequestStatus::Pending === $returnRequest->getStatus()) {
            $returnRequest->setStatus(ReturnRequestStatus::Rejected);
            $this->entityManager->flush();
            $this->addFlash('warning', 'Demande de retour refusée.');
        }

        return $this->redirectToRoute('app_vendor_returns');
    }

    #[Route('/{id}/rembourser', name: 'app_vendor_returns_refund', methods: ['POST'])]
    public function refund(ReturnRequest $returnRequest, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $vendor = $shop->getOwner();
        $allowed = $vendor ? $this->returnRequestRepository->findOneForVendor($vendor, $returnRequest->getId() ?? 0) : null;
        if (!$allowed) {
            throw $this->createNotFoundException('Demande introuvable.');
        }
        if (!$this->isCsrfTokenValid('refund_return_'.$returnRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        if (ReturnRequestStatus::Approved !== $returnRequest->getStatus()) {
            $this->addFlash('warning', 'Le retour doit être validé avant remboursement.');

            return $this->redirectToRoute('app_vendor_returns');
        }

        $order = $returnRequest->getOrder();
        if (!$order) {
            $this->addFlash('danger', 'Commande introuvable.');

            return $this->redirectToRoute('app_vendor_returns');
        }

        if ($order->getRefundedAt()) {
            $this->addFlash('warning', 'Commande déjà remboursée.');

            return $this->redirectToRoute('app_vendor_returns');
        }

        $status = $order->getStatusEnum();
        $isEligible = match ($status) {
            OrderStatus::Paid, OrderStatus::Shipped => true,
            default => false,
        };
        if (!$isEligible) {
            $this->addFlash('danger', 'Commande non éligible au remboursement.');

            return $this->redirectToRoute('app_vendor_returns');
        }

        $amountCents = null;
        $amountRaw = trim((string) $request->request->get('refund_amount', ''));
        if ('' !== $amountRaw) {
            $normalized = str_replace(',', '.', $amountRaw);
            if (!is_numeric($normalized)) {
                $this->addFlash('danger', 'Montant invalide.');

                return $this->redirectToRoute('app_vendor_returns');
            }

            $amount = (float) $normalized;
            if ($amount <= 0) {
                $this->addFlash('danger', 'Le montant doit être supérieur à 0.');

                return $this->redirectToRoute('app_vendor_returns');
            }

            $totalCents = (int) round(((float) $order->getTotalAmount()) * 100);
            $amountCents = (int) round($amount * 100);
            if ($amountCents > $totalCents) {
                $this->addFlash('danger', 'Le montant dépasse le total de la commande.');

                return $this->redirectToRoute('app_vendor_returns');
            }
        }

        try {
            $refund = $this->stripePaymentService->refundPayment($order, $amountCents);
        } catch (\RuntimeException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('app_vendor_returns');
        }

        $order
            ->setRefundId($refund['id'])
            ->setRefundedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', 'Remboursement effectué.');

        return $this->redirectToRoute('app_vendor_returns');
    }

    private function resolveShop(Request $request): \App\Entity\Shop
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            throw $this->createAccessDeniedException($response->getContent() ?? 'Accès refusé.');
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createNotFoundException('Aucun vendeur trouvé.');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop) {
            throw $this->createNotFoundException('Aucune boutique enregistrée.');
        }

        return $shop;
    }

    private function resolveViewer(Request $request): User
    {
        $current = $this->security->getUser();
        if ($current instanceof User) {
            return $current;
        }

        $recentId = $request->getSession()->get('recent_user_id');
        if ($recentId) {
            $user = $this->userRepository->find((int) $recentId);
            if ($user instanceof User) {
                return $user;
            }
        }

        throw $this->createAccessDeniedException('Utilisateur requis.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildVendorNav(string $activeRoute): array
    {
        return [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => 'app_vendor_shop_new' === $activeRoute, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => 'app_vendor_products' === $activeRoute, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => 'app_vendor_attributes' === $activeRoute, 'path' => 'app_vendor_attributes'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => 'app_vendor_orders' === $activeRoute, 'path' => 'app_vendor_orders'],
            ['label' => 'Retours', 'icon' => '↩️', 'active' => 'app_vendor_returns' === $activeRoute, 'path' => 'app_vendor_returns'],
            ['label' => 'Livraison', 'icon' => '🚚', 'active' => 'app_vendor_shipping_index' === $activeRoute, 'path' => 'app_vendor_shipping_index'],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => 'app_vendor_stats' === $activeRoute, 'path' => 'app_vendor_stats'],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];
    }
}
