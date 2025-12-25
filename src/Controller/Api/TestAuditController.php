<?php

namespace App\Controller\Api;

use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Permet de tester rapidement la chaîne d'audit (utile pour la soutenance).
 */
class TestAuditController extends AbstractController
{
    #[Route('/api/test-audit', name: 'api_test_audit', methods: ['GET'])]
    #[OA\Get(
        path: '/api/test-audit',
        summary: 'Génère une entrée d’audit de test',
        tags: ['System'],
        security: [['BearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Audit enregistré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'message', type: 'string', example: 'Audit log created successfully'),
                    ]
                )
            )
        ]
    )]
    public function index(AuditLoggerService $audit): JsonResponse
    {
        // On écrit volontairement une entrée pour vérifier la base et le service
        $audit->log(
            action: AuditAction::AuditTest,
            resource: 'test_endpoint',
            data: ['message' => 'Audit de test OK']
        );

        return $this->json([
            'status' => 'ok',
            'message' => 'Audit log created successfully',
        ]);
    }
}
