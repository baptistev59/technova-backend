<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Vendor;
use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::preUpdate)]
final class VendorAuditSubscriber
{
    public function __construct(
        private readonly AuditLoggerService $auditLogger,
        private readonly Security $security,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof Vendor) {
            return;
        }

        if (!$event->hasChangedField('isSuspended')) {
            return;
        }

        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $newValue = (bool) $event->getNewValue('isSuspended');
        $action = $newValue ? AuditAction::AdminVendorSuspend : AuditAction::AdminVendorActivate;

        $this->auditLogger->log(
            action: $action,
            resource: 'vendor',
            resourceId: $entity->getId(),
            data: [
                'company' => $entity->getCompanyName(),
                'from' => $event->getOldValue('isSuspended'),
                'to' => $newValue,
            ]
        );
    }
}
