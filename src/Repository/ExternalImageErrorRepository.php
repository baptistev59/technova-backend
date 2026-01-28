<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExternalImageError;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalImageError>
 */
final class ExternalImageErrorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalImageError::class);
    }

    public function findOneByUrl(string $url): ?ExternalImageError
    {
        return $this->findOneBy(['url' => $url]);
    }
}
