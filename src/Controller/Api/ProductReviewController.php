<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\ProductReview;
use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/products/{id}/reviews')]
#[OA\Tag(name: 'Reviews')]
final class ProductReviewController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductReviewRepository $reviewRepository,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_product_reviews_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les avis approuvés d’un produit',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Avis'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
        ]
    )]
    public function index(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $reviews = $this->reviewRepository->findApprovedForProduct($product->getId());
        $payload = array_map(static fn (ProductReview $review) => [
            'id' => $review->getId(),
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'createdAt' => $review->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], $reviews);

        return $this->json($payload);
    }

    #[Route('', name: 'api_product_reviews_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        summary: 'Créer ou mettre à jour un avis (client ayant acheté)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rating'],
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', minimum: 0, maximum: 5, example: 4),
                    new OA\Property(property: 'comment', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Avis enregistré'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 403, description: 'Achat requis'),
        ]
    )]
    public function create(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $product = $this->productRepository->find($id);
        if (!$product instanceof Product) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $requestData = json_decode($request->getContent() ?: '[]', true) ?? [];
        $ratingRaw = $requestData['rating'] ?? null;
        $rating = filter_var($ratingRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 5],
        ]);
        $comment = isset($requestData['comment']) ? trim((string) $requestData['comment']) : null;

        if (false === $rating) {
            return $this->json(['message' => 'Note invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->hasPurchasedProduct($user, $product->getId())) {
            return $this->json(['message' => 'Achat requis pour laisser un avis.'], Response::HTTP_FORBIDDEN);
        }

        $review = $this->reviewRepository->findOneBy(['author' => $user, 'product' => $product]);
        if ($review instanceof ProductReview) {
            $review
                ->setRating((float) $rating)
                ->setComment($comment);
        } else {
            $review = (new ProductReview())
                ->setAuthor($user)
                ->setProduct($product)
                ->setRating((float) $rating)
                ->setComment($comment);
            $this->entityManager->persist($review);
        }

        $this->entityManager->flush();

        return $this->json([
            'status' => 'saved',
            'review' => [
                'id' => $review->getId(),
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
            ],
        ]);
    }

    private function hasPurchasedProduct(User $user, int $productId): bool
    {
        $orders = $this->orderRepository->findBy(['owner' => $user, 'status' => 'paid']);
        if ([] === $orders) {
            return false;
        }

        foreach ($orders as $order) {
            if (!$order instanceof CustomerOrder) {
                continue;
            }
            foreach ($order->getItems() as $item) {
                if ($item->getProductId() === $productId) {
                    return true;
                }
            }
        }

        return false;
    }
}
