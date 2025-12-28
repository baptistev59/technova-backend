<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\CustomerOrder;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrderVoter extends Voter
{
    public const MANAGE = 'ORDER_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof CustomerOrder;
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

        $vendor = $user->getVendor();
        if (!$vendor) {
            return false;
        }

        $shopIds = [];
        foreach ($vendor->getShops() as $shop) {
            if (null !== $shop->getId()) {
                $shopIds[] = $shop->getId();
            }
        }

        if ([] === $shopIds) {
            return false;
        }

        foreach ($subject->getItems() as $item) {
            $shopId = $item->getShopId();
            if (null !== $shopId && in_array($shopId, $shopIds, true)) {
                return true;
            }
        }

        return false;
    }
}
