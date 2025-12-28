<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\UserRepository;
use App\Security\ViewerAccessChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-compte/commandes')]
class AccountOrderController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly UserRepository $userRepository,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly ViewerAccessChecker $viewerAccessChecker,
    ) {
    }

    #[Route('', name: 'app_account_orders', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $orders = $this->orderRepository->findBy(['owner' => $user], ['createdAt' => 'DESC']);

        return $this->render('account/orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/{reference}', name: 'app_account_orders_show', methods: ['GET'])]
    public function show(string $reference, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $order = $this->orderRepository->findOneBy(['reference' => $reference]);
        $user = $this->resolveViewer($request);

        if (!$order instanceof CustomerOrder || $order->getOwner()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('account/orders/show.html.twig', [
            'order' => $order,
        ]);
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
}
