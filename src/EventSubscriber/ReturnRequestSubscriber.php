<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ReturnRequest;
use App\Enum\ReturnRequestStatus;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ReturnRequestSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly ?string $mailerFrom = null,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [Events::preUpdate];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ReturnRequest) {
            return;
        }

        if (!$args->hasChangedField('status')) {
            return;
        }

        $newStatus = $args->getNewValue('status');
        if (!$newStatus instanceof ReturnRequestStatus) {
            return;
        }

        if (!in_array($newStatus, [ReturnRequestStatus::Approved, ReturnRequestStatus::Rejected], true)) {
            return;
        }

        $email = $entity->getRequester()?->getEmail();
        if (!$email) {
            return;
        }

        $fromAddress = $this->mailerFrom
            ? Address::create($this->mailerFrom)
            : new Address('no-reply@technova.local', 'TechNova');

        $subject = match ($newStatus) {
            ReturnRequestStatus::Approved => 'TechNova — Retour validé',
            ReturnRequestStatus::Rejected => 'TechNova — Retour refusé',
            default => 'TechNova — Mise à jour retour',
        };

        $message = (new TemplatedEmail())
            ->from($fromAddress)
            ->to($email)
            ->subject($subject)
            ->htmlTemplate('emails/return_request_status.html.twig')
            ->textTemplate('emails/return_request_status.text.twig')
            ->context([
                'return_request' => $entity,
                'status' => $newStatus->value,
            ]);

        $this->mailer->send($message);
    }
}
