<?php

namespace App\Security;

use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Handler custom pour uniformiser les retours JSON lors d'un login raté.
 */
class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly AuditLoggerService $audit,
        #[Autowire(service: 'monolog.logger.security')]
        private readonly LoggerInterface $securityLogger
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $email = $request->toArray()['email'] ?? null;

        // 🔥 Audit automatique
        $this->audit->log(
            action: AuditAction::LoginFailure,
            resource: 'user',
            data: [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]
        );
        $this->securityLogger->warning('Login failure', [
            'email' => $email,
            'error' => $exception->getMessage(),
        ]);

        // Réponse standard Lexik
        return new JsonResponse([
            'code' => 401,
            'message' => 'Invalid credentials.'
        ], 401);
    }
}
