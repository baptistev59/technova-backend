<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\SavedCartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

class UserAnonymizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        private readonly SavedCartRepository $savedCartRepository,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function anonymize(User $user): void
    {
        $this->removeAvatarFile($user);

        $user->setEmail($this->generateAnonymousEmail($user->getId()))
            ->setFirstname('Compte')
            ->setLastname('Supprimé')
            ->setPhone(null)
            ->setAvatarPath(null)
            ->setRoles([])
            ->setIsDeleted(true)
            ->setNewsletterOptIn(false);

        foreach ($user->getAddresses() as $address) {
            $this->entityManager->remove($address);
        }

        if ($cart = $this->savedCartRepository->findOneBy(['owner' => $user])) {
            $this->entityManager->remove($cart);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function generateAnonymousEmail(?int $id): string
    {
        $suffix = $id ?? random_int(1000, 999999);

        return sprintf('deleted-%s@technova.local', $suffix);
    }

    private function removeAvatarFile(User $user): void
    {
        $avatarPath = $user->getAvatarPath();
        if (!$avatarPath || !str_starts_with($avatarPath, 'uploads/avatars/')) {
            return;
        }

        $fullPath = sprintf('%s/public/%s', rtrim($this->projectDir, '/'), $avatarPath);
        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
        }
    }
}
