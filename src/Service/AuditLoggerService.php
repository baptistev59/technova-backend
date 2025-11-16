<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLoggerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack,
    ) {}

    public function log(
        string $action,
        ?string $resource = null,
        ?int $resourceId = null,
        ?array $data = null
    ): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $log = new AuditLog();

        // User connecté (si existe)
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $log->setOwner($user);
        }

        // Action
        $log->setAction($action);

        // Ressource & ID
        $log->setResource($resource);
        $log->setResourceId($resourceId);

        // IP + User Agent
        if ($request) {
            $log->setIpAddress($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
        }

        // Additional data
        if ($data !== null) {
            $log->setData($data);
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}
