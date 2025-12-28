<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TwoFactorAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditLoggerService $auditLogger,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwoFactorAuthenticationEvents::COMPLETE => 'onTwoFactorComplete',
        ];
    }

    public function onTwoFactorComplete(TwoFactorAuthenticationEvent $event): void
    {
        $token = $event->getToken();
        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $provider = null;
        if ($token instanceof TwoFactorTokenInterface) {
            $provider = $token->getCurrentTwoFactorProvider();
        }

        $user->clearEmailAuthCode();
        $session = $event->getRequest()->getSession();
        $session->set('recent_user_id', $user->getId());
        $session->set('jwt_token', $this->jwtManager->create($user));

        $this->auditLogger->log(
            action: AuditAction::TwoFactorSuccess,
            resource: 'user',
            resourceId: $user->getId(),
            data: [
                'provider' => $provider,
            ]
        );
    }
}
