<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShippingMethod;
use App\Entity\ShippingZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingMethod>
 */
final class ShippingMethodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingMethod::class);
    }

    /**
     * @return list<ShippingMethod>
     */
    public function findActiveByZone(ShippingZone $zone): array
    {
        return $this->createQueryBuilder('method')
            ->andWhere('method.zone = :zone')
            ->andWhere('method.isActive = true')
            ->setParameter('zone', $zone)
            ->orderBy('method.sortOrder', 'ASC')
            ->addOrderBy('method.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
