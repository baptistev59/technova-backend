<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;


class TestApiController extends AbstractController
{ #[Route('/api/test', name: 'api_test', methods: ['GET'])]
    public function index(LoggerInterface $technovaLogger): JsonResponse
    {
        // Écriture dans le canal "technova"
        $technovaLogger->info('Appel réussi sur /api/test depuis React');

        return $this->json([
            'status' => 'ok',
            'message' => 'TechNova API répond bien 🚀',
        ]);
    }
}