<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttributeDefinition;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeDefinition>
 */
class AttributeDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeDefinition::class);
    }

    /**
     * @return AttributeDefinition[]
     */
    public function findByShopWithValues(Shop $shop): array
    {
        return $this->createQueryBuilder('attribute')
            ->addSelect('value')
            ->leftJoin('attribute.values', 'value')
            ->andWhere('attribute.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('attribute.position', 'ASC')
            ->addOrderBy('attribute.name', 'ASC')
            ->addOrderBy('attribute.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
