<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Enum\AuditAction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service centralisé pour tracer les actions importantes (logins, commandes, etc.).
 * On peut l'appeler depuis n'importe quel contrôleur/handler/subscriber.
 */
class AuditLoggerService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Petite API interne : on passe l'action + ressource + données contextuelles.
     */
    public function log(
        AuditAction|string $action,
        ?string $resource = null,
        ?int $resourceId = null,
        ?array $data = null,
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $log = new AuditLog();

        // User connecté (si existe)
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $log->setOwner($user);
        }

        // Action
        $log->setAction($action instanceof AuditAction ? $action->value : $action);

        // Ressource & ID
        $log->setResource($resource);
        $log->setResourceId($resourceId);

        // IP + User Agent
        if ($request) {
            $log->setIpAddress($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
        }

        // Additional data
        if (null !== $data) {
            $log->setData($data);
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}
