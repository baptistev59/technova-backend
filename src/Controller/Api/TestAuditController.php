<?php

namespace App\Controller\Api;

use App\Service\AuditLoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestAuditController extends AbstractController
{
    #[Route('/api/test-audit', name: 'api_test_audit', methods: ['GET'])]
    public function index(AuditLoggerService $audit): JsonResponse
    {
        $audit->log(
            action: 'TEST_AUDIT',
            resource: 'test_endpoint',
            data: ['message' => 'Audit de test OK']
        );

        return $this->json([
            'status' => 'ok',
            'message' => 'Audit log created successfully',
        ]);
    }
}
