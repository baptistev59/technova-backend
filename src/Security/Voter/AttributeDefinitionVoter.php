<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AttributeDefinition;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AttributeDefinitionVoter extends Voter
{
    public const MANAGE = 'ATTRIBUTE_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof AttributeDefinition;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $shopOwner = $subject->getShop()?->getOwner()?->getOwner();

        return $shopOwner instanceof User && $shopOwner->getId() === $user->getId();
    }
}
