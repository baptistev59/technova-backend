<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use App\Entity\User;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Scheb\TwoFactorBundle\Model\PersisterInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;

final class ExpiringEmailCodeGenerator implements CodeGeneratorInterface
{
    public function __construct(
        private readonly PersisterInterface $persister,
        private readonly AuthCodeMailerInterface $mailer,
        private readonly int $digits,
        private readonly int $ttlSeconds,
    ) {
    }

    public function generateAndSend(TwoFactorInterface $user): void
    {
        $min = 10 ** ($this->digits - 1);
        $max = 10 ** $this->digits - 1;
        $code = (string) random_int($min, $max);

        $user->setEmailAuthCode($code);
        if ($user instanceof User) {
            $user->setEmailAuthCodeExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', $this->ttlSeconds)));
        }

        $this->persister->persist($user);
        $this->mailer->sendAuthCode($user);
    }

    public function reSend(TwoFactorInterface $user): void
    {
        $this->mailer->sendAuthCode($user);
    }
}
