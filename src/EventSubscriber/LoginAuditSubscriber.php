<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Observe les succès/échecs de connexion pour alimenter la table audit_log.
 */
class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditLoggerService $audit,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            AuthenticationFailureEvent::class => 'onLoginFailure',
        ];
    }

    /**
     * Trace chaque succès de connexion pour alimenter l'audit.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        /** @var User|null $user */
        $user = $event->getUser();
        $resourceId = null;
        $email = null;

        if ($user instanceof User) {
            $resourceId = $user->getId();
            $email = $user->getUserIdentifier();
        }

        // Journalise l'ID utilisateur + email utilisé
        $this->audit->log(
            action: AuditAction::LoginSuccess,
            resource: 'user',
            resourceId: $resourceId,
            data: [
                'email' => $email,
            ]
        );
    }

    /**
     * Capture les tentatives échouées pour surveiller les attaques/bruteforce.
     */
    public function onLoginFailure(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();
        $requestData = [];
        if ($request = $event->getRequest()) {
            try {
                $requestData = $request->toArray();
            } catch (\Throwable) {
                // toArray() peut lancer une exception si le body n'est pas JSON → on ignore
            }
        }
        $email = $requestData['email'] ?? null;

        // Ici on n'a pas d'entité User mais on garde le login tenté + le message
        $this->audit->log(
            action: AuditAction::LoginFailure,
            resource: 'user',
            data: [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]
        );
    }
}
