<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserProfileService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function guessPrimaryAddress(User $user): ?Address
    {
        foreach ($user->getAddresses() as $address) {
            if ($address->isDefault()) {
                return $address;
            }
        }

        return $user->getAddresses()->first() ?: null;
    }

    public function applyProfileUpdates(User $user, ?Address $primaryAddress): void
    {
        if ($primaryAddress instanceof Address && $primaryAddress->getAddressLine1()) {
            $address = $this->guessPrimaryAddress($user) ?? new Address();
            $address
                ->setLabel($primaryAddress->getLabel())
                ->setAddressLine1($primaryAddress->getAddressLine1())
                ->setAddressLine2($primaryAddress->getAddressLine2())
                ->setPostalCode($primaryAddress->getPostalCode())
                ->setCity($primaryAddress->getCity())
                ->setState($primaryAddress->getState())
                ->setCountry($primaryAddress->getCountry() ?: 'FR')
                ->setIsDefault($primaryAddress->isDefault() ?? true)
                ->setIsShipping($primaryAddress->isShipping() ?? true)
                ->setIsBilling($primaryAddress->isBilling() ?? true)
                ->setOwner($user);

            if (!$user->getAddresses()->contains($address)) {
                $user->addAddress($address);
            }
            $this->entityManager->persist($address);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function addressFromArray(?array $data): ?Address
    {
        if (!$data) {
            return null;
        }

        $address = new Address();
        $address
            ->setLabel($data['label'] ?? null)
            ->setAddressLine1($data['addressLine1'] ?? null)
            ->setAddressLine2($data['addressLine2'] ?? null)
            ->setPostalCode($data['postalCode'] ?? null)
            ->setCity($data['city'] ?? null)
            ->setState($data['state'] ?? null)
            ->setCountry($data['country'] ?? 'FR')
            ->setIsDefault((bool) ($data['isDefault'] ?? true))
            ->setIsShipping((bool) ($data['isShipping'] ?? true))
            ->setIsBilling((bool) ($data['isBilling'] ?? true));

        return $address;
    }

    public function profileToArray(User $user): array
    {
        $address = $this->guessPrimaryAddress($user);

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'phone' => $user->getPhone(),
            'avatarPath' => $user->getAvatarPath(),
            'newsletterOptIn' => $user->isNewsletterOptIn(),
            'address' => $address ? $this->addressToArray($address) : null,
        ];
    }

    private function addressToArray(Address $address): array
    {
        return [
            'label' => $address->getLabel(),
            'addressLine1' => $address->getAddressLine1(),
            'addressLine2' => $address->getAddressLine2(),
            'postalCode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'state' => $address->getState(),
            'country' => $address->getCountry(),
            'isDefault' => $address->isDefault(),
            'isShipping' => $address->isShipping(),
            'isBilling' => $address->isBilling(),
        ];
    }
}
