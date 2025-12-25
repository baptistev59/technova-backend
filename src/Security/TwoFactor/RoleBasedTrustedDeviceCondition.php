<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use App\Entity\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Trusted\TrustedDeviceManagerInterface;

final class RoleBasedTrustedDeviceCondition implements TwoFactorConditionInterface
{
    public function __construct(
        private readonly TrustedDeviceManagerInterface $trustedDeviceManager,
        private readonly bool $extendTrustedToken,
    ) {
    }

    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();
        $firewallName = $context->getFirewallName();

        if ($user instanceof User && $this->requiresTotp($user)) {
            return true;
        }

        if ($this->trustedDeviceManager->isTrustedDevice($user, $firewallName)) {
            if (
                $this->extendTrustedToken
                && $this->trustedDeviceManager->canSetTrustedDevice($user, $context->getRequest(), $firewallName)
            ) {
                $this->trustedDeviceManager->addTrustedDevice($user, $firewallName);
            }

            return false;
        }

        return true;
    }

    private function requiresTotp(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_VENDOR', $roles, true);
    }
}
