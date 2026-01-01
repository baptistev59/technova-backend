<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShippingMethod;
use App\Entity\ShippingRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingRate>
 */
final class ShippingRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingRate::class);
    }

    public function findRateForWeight(ShippingMethod $method, float $weight): ?ShippingRate
    {
        return $this->createQueryBuilder('rate')
            ->andWhere('rate.method = :method')
            ->andWhere('rate.minWeight <= :weight')
            ->andWhere('rate.maxWeight IS NULL OR rate.maxWeight >= :weight')
            ->setParameter('method', $method)
            ->setParameter('weight', $weight)
            ->orderBy('CASE WHEN rate.maxWeight IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('rate.maxWeight', 'ASC')
            ->addOrderBy('rate.minWeight', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
