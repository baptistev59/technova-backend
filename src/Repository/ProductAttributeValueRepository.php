<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductAttributeValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductAttributeValue>
 *
 * @method ProductAttributeValue|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductAttributeValue|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductAttributeValue[]    findAll()
 * @method ProductAttributeValue[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductAttributeValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductAttributeValue::class);
    }
}
