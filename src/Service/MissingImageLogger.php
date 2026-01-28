<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ExternalImageErrorRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MissingImageLogger
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ExternalImageErrorRepository $repo)
    {
    }

    public function log(string $url, int $statusCode): void
    {
        $existing = $this->repo->findOneByUrl($url);
        if (null === $existing) {
            $entityClass = $this->repo->getClassName();
            $entity = new $entityClass($url, $statusCode);
            $this->em->persist($entity);
        } else {
            $existing->increment($statusCode);
            $this->em->persist($existing);
        }

        $this->em->flush();
    }
}
