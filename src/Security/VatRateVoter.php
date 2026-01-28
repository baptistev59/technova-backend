<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Entity\VatRate;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class VatRateVoter extends Voter
{
    private const VIEW = 'VIEW';
    private const EDIT = 'EDIT';
    private const DELETE = 'DELETE';
    private const CREATE = 'CREATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE], true)) {
            return false;
        }

        // subject can be VatRate or null (for creation checks)
        return null === $subject || $subject instanceof VatRate;
    }

    /**
     * @param VatRate|null $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // anonymous users not allowed
        if (!$user instanceof User) {
            return false;
        }

        // admins have full access
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // vendors can manage rates only for their own shop
        if (in_array('ROLE_VENDOR', $user->getRoles(), true)) {
            $vendor = $user->getVendor();
            if (null === $vendor) {
                return false;
            }

            // For create, subject may be a VatRate (filled) or null -> allow only if the VatRate references a shop owned by vendor
            $vat = $subject instanceof VatRate ? $subject : null;

            switch ($attribute) {
                case self::CREATE:
                case self::EDIT:
                case self::DELETE:
                case self::VIEW:
                    if (null === $vat) {
                        // no vat instance to check ownership -> deny
                        return false;
                    }

                    $shop = $vat->getShop();
                    if (null === $shop) {
                        return false; // vendors cannot manage global rates
                    }

                    $owner = $shop->getOwner();
                    return null !== $owner && $owner->getId() === $vendor->getId();
            }
        }

        return false;
    }
}
