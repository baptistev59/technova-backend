<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShippingZone;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingZone>
 */
final class ShippingZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingZone::class);
    }

    /**
     * @return list<ShippingZone>
     */
    public function findActiveByShop(Shop $shop): array
    {
        return $this->createQueryBuilder('zone')
            ->andWhere('zone.shop = :shop')
            ->andWhere('zone.isActive = true')
            ->setParameter('shop', $shop)
            ->orderBy('zone.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
