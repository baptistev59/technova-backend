<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\CustomerOrder;
use App\Entity\ProductReview;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use App\Repository\UserRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
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
        private readonly ProductRepository $productRepository,
        private readonly ProductReviewRepository $reviewRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        #[Autowire(service: 'html_sanitizer.sanitizer.rich_text')]
        private readonly HtmlSanitizerInterface $richTextSanitizer,
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

        $productIds = array_values(array_unique(array_map(
            static fn ($item) => $item->getProductId(),
            $order->getItems()->toArray()
        )));
        $reviews = $this->reviewRepository->findForAuthorAndProducts($user, $productIds);
        $reviewsByProduct = [];
        foreach ($reviews as $review) {
            $productId = $review->getProduct()?->getId();
            if (null !== $productId) {
                $reviewsByProduct[$productId] = $review;
            }
        }

        return $this->render('account/orders/show.html.twig', [
            'order' => $order,
            'reviews_by_product' => $reviewsByProduct,
        ]);
    }

    #[Route('/{reference}/avis', name: 'app_account_orders_review', methods: ['POST'])]
    public function submitReview(string $reference, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $order = $this->orderRepository->findOneBy(['reference' => $reference]);
        $user = $this->resolveViewer($request);

        if (!$order instanceof CustomerOrder || $order->getOwner()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        if (!$order->isPaid()) {
            $this->addFlash('error', 'Les avis sont disponibles uniquement après paiement.');

            return $this->redirectToRoute('app_account_orders_show', ['reference' => $reference]);
        }

        $productId = (int) $request->request->get('product_id', 0);
        $ratingRaw = (string) $request->request->get('rating', '');
        $rating = filter_var($ratingRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 5],
        ]);
        $comment = trim((string) $request->request->get('comment', ''));

        if (!$this->isCsrfTokenValid('review_'.$order->getId().'_'.$productId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($productId <= 0 || false === $rating) {
            $this->addFlash('error', 'Merci de sélectionner une note valide.');

            return $this->redirectToRoute('app_account_orders_show', ['reference' => $reference]);
        }

        $purchased = array_filter(
            $order->getItems()->toArray(),
            static fn ($item) => $item->getProductId() === $productId
        );
        if ([] === $purchased) {
            $this->addFlash('error', 'Ce produit ne figure pas dans cette commande.');

            return $this->redirectToRoute('app_account_orders_show', ['reference' => $reference]);
        }

        $product = $this->productRepository->find($productId);
        if (null === $product) {
            $this->addFlash('error', 'Produit introuvable.');

            return $this->redirectToRoute('app_account_orders_show', ['reference' => $reference]);
        }

        $review = $this->reviewRepository->findOneBy(['author' => $user, 'product' => $product]);
        $sanitizedComment = $this->sanitizeRichText('' !== $comment ? $comment : null);
        if ($review instanceof ProductReview) {
            $review
            ->setRating((float) $rating)
            ->setComment($sanitizedComment);
            $this->addFlash('success', 'Avis mis à jour.');
        } else {
            $review = (new ProductReview())
                ->setAuthor($user)
                ->setProduct($product)
                ->setRating($rating)
                ->setComment($sanitizedComment);
            $this->entityManager->persist($review);
            $this->addFlash('success', 'Merci pour votre avis !');
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('app_account_orders_show', ['reference' => $reference]);
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

    private function sanitizeRichText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $sanitized = trim($this->richTextSanitizer->sanitize($value));

        return '' === $sanitized ? null : $sanitized;
    }
}
