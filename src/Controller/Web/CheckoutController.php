<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\UserRepository;
use App\Security\ViewerAccessChecker;
use App\Service\CartService;
use App\Service\CheckoutService;
use App\Service\StripePaymentService;
use App\Service\UserProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/commande')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly CartService $cartService,
        private readonly UserProfileService $profileService,
        private readonly CheckoutService $checkoutService,
        private readonly UserRepository $userRepository,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly StripePaymentService $stripePaymentService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('', name: 'app_checkout_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        $summary = $this->cartService->getSummary();
        if (empty($summary['items'])) {
            $this->addFlash('warning', 'Votre panier est vide. Sélectionnez des produits avant de passer commande.');

            return $this->redirectToRoute('app_cart_show');
        }

        $user = $this->resolveViewer($request);
        $address = $this->profileService->guessPrimaryAddress($user);
        if (!$address) {
            $this->addFlash('warning', 'Ajoutez une adresse dans votre profil avant de confirmer la commande.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('checkout/index.html.twig', [
            'cart' => $summary,
            'address' => $address,
            'viewer' => $user,
        ]);
    }

    #[Route('/confirmer', name: 'app_checkout_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }
        if (!$this->isCsrfTokenValid('checkout_confirm', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $user = $this->resolveViewer($request);
        $address = $this->profileService->guessPrimaryAddress($user);
        if (!$address) {
            $this->addFlash('warning', 'Ajoutez une adresse dans votre profil avant de confirmer la commande.');

            return $this->redirectToRoute('app_profile');
        }

        try {
            $order = $this->checkoutService->createOrder($user, $address);
        } catch (\RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_cart_show');
        }

        $successUrl = $this->urlGenerator->generate(
            'app_checkout_success',
            ['reference' => $order->getReference()],
            UrlGeneratorInterface::ABSOLUTE_URL
        ).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $this->urlGenerator->generate(
            'app_checkout_cancel',
            ['reference' => $order->getReference()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $session = $this->stripePaymentService->createCheckoutSession($order, $successUrl, $cancelUrl);
            $this->checkoutService->attachPaymentSession($order, $session['id']);
        } catch (\Throwable $exception) {
            $this->checkoutService->cancelOrder($order);
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('app_cart_show');
        }

        return $this->redirect($session['url']);
    }

    #[Route('/confirmee/{reference}', name: 'app_checkout_success', methods: ['GET'])]
    public function success(string $reference, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        $order = $this->orderRepository->findOneBy(['reference' => $reference]);
        if (!$order || !$this->ownsOrder($order, $request)) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        $this->cartService->clear();

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/annulee/{reference}', name: 'app_checkout_cancel', methods: ['GET'])]
    public function cancel(string $reference, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        $order = $this->orderRepository->findOneBy(['reference' => $reference]);
        if ($order && $this->ownsOrder($order, $request)) {
            $this->addFlash('warning', 'Paiement annulé. Ton panier reste disponible pour retenter un règlement.');
        }

        return $this->redirectToRoute('app_cart_show');
    }

    private function resolveViewer(Request $request): User
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $user;
        }

        $session = $request->getSession();
        if ($session->has('recent_user_id')) {
            $resolved = $this->userRepository->find((int) $session->get('recent_user_id'));
            if ($resolved instanceof User) {
                return $resolved;
            }
        }

        throw $this->createAccessDeniedException('Utilisateur requis.');
    }

    private function ownsOrder(CustomerOrder $order, Request $request): bool
    {
        $user = $this->getUser();
        if ($user instanceof User && $order->getOwner()?->getId() === $user->getId()) {
            return true;
        }

        $session = $request->getSession();
        if ($session->has('recent_user_id')) {
            return $order->getOwner()?->getId() === (int) $session->get('recent_user_id');
        }

        return false;
    }
}
