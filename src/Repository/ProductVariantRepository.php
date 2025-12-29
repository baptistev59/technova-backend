<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductVariant;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariant>
 *
 * @method ProductVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductVariant[]    findAll()
 * @method ProductVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    public function countLowStockForShop(Shop $shop, int $threshold): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->innerJoin('v.product', 'p')
            ->andWhere('p.shop = :shop')
            ->andWhere('p.type = :type')
            ->andWhere('v.stock <= COALESCE(v.lowStockThreshold, p.lowStockThreshold, :threshold)')
            ->setParameter('shop', $shop)
            ->setParameter('type', 'variable')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
