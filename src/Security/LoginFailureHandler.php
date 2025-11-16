<?php

namespace App\Security;

use App\Service\AuditLoggerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private AuditLoggerService $audit
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $email = $request->toArray()['email'] ?? null;

        // 🔥 Audit automatique
        $this->audit->log(
            action: 'LOGIN_FAILURE',
            resource: 'user',
            data: [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]
        );

        // Réponse standard Lexik
        return new JsonResponse([
            'code' => 401,
            'message' => 'Invalid credentials.'
        ], 401);
    }
}
