<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ProductReview;
use App\Entity\ProductReviewReport;
use App\Entity\User;
use App\Repository\ProductReviewReportRepository;
use App\Repository\ProductReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/report')]
#[OA\Tag(name: 'Reviews')]
#[IsGranted('ROLE_USER')]
final class ReviewReportController extends AbstractController
{
    public function __construct(
        private readonly ProductReviewRepository $reviewRepository,
        private readonly ProductReviewReportRepository $reportRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_review_report', methods: ['POST'])]
    #[OA\Post(
        summary: 'Signaler un avis produit',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['reviewId', 'reason'],
                properties: [
                    new OA\Property(property: 'reviewId', type: 'integer', example: 55),
                    new OA\Property(property: 'reason', type: 'string', example: 'Contenu abusif'),
                    new OA\Property(property: 'details', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Signalement enregistré'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 404, description: 'Avis introuvable'),
        ]
    )]
    public function report(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent() ?: '[]', true) ?? [];
        $reviewId = isset($payload['reviewId']) ? (int) $payload['reviewId'] : 0;
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reviewId <= 0 || '' === $reason) {
            return $this->json(['message' => 'reviewId et reason requis.'], Response::HTTP_BAD_REQUEST);
        }

        $review = $this->reviewRepository->find($reviewId);
        if (!$review instanceof ProductReview) {
            return $this->json(['message' => 'Avis introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $existing = $this->reportRepository->findOneBy([
            'review' => $review,
            'reporter' => $user,
        ]);
        if ($existing instanceof ProductReviewReport) {
            return $this->json(['message' => 'Signalement déjà enregistré.'], Response::HTTP_BAD_REQUEST);
        }

        $report = (new ProductReviewReport())
            ->setReview($review)
            ->setReporter($user)
            ->setReason($reason)
            ->setDetails(isset($payload['details']) ? trim((string) $payload['details']) : null);

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $this->json([
            'status' => 'submitted',
            'report' => [
                'id' => $report->getId(),
                'reviewId' => $review->getId(),
                'reason' => $report->getReason(),
                'details' => $report->getDetails(),
                'status' => $report->getStatus()->value,
            ],
        ], Response::HTTP_CREATED);
    }
}
