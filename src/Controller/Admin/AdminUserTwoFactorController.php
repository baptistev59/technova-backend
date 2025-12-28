<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminUserTwoFactorController extends AbstractController
{
    public function __construct(
        private readonly AuditLoggerService $auditLogger,
    ) {
    }

    #[Route('/admin/users/{id}/2fa/reset', name: 'admin_user_2fa_reset', methods: ['GET'])]
    public function reset(User $user, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $token = (string) $request->query->get('_token');
        if (!$this->isCsrfTokenValid('reset2fa'.$user->getId(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user
            ->clearTotpSecret()
            ->clearEmailAuthCode()
            ->bumpTrustedTokenVersion();

        $entityManager->flush();

        $this->auditLogger->log(
            action: AuditAction::AdminTwoFactorReset,
            resource: 'user',
            resourceId: $user->getId(),
            data: [
                'email' => $user->getEmail(),
            ]
        );

        $this->addFlash('success', '2FA reinitialisee.');

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_ea'));
    }
}
