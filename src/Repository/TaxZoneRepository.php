<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\TaxZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxZone>
 *
 * @method TaxZone|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaxZone|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaxZone[]    findAll()
 * @method TaxZone[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class TaxZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaxZone::class);
    }

    /**
     * Récupérer les zones prédéfinies.
     *
     * @return TaxZone[]
     */
    public function findPresets(): array
    {
        return $this->createQueryBuilder('tz')
            ->andWhere('tz.isPreset = true')
            ->orderBy('tz.sortOrder', 'ASC')
            ->addOrderBy('tz.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les zones personnalisées d'une boutique.
     *
     * @return TaxZone[]
     */
    public function findCustomByShop(Shop $shop): array
    {
        return $this->createQueryBuilder('tz')
            ->andWhere('tz.shop = :shop')
            ->andWhere('tz.isPreset = false')
            ->setParameter('shop', $shop)
            ->orderBy('tz.sortOrder', 'ASC')
            ->addOrderBy('tz.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les zones actives par boutique (prédéfinies + perso).
     *
     * @return TaxZone[]
     */
    public function findActiveByShop(Shop $shop): array
    {
        return $this->createQueryBuilder('tz')
            ->andWhere('(tz.isPreset = true OR tz.shop = :shop)')
            ->andWhere('tz.active = true')
            ->setParameter('shop', $shop)
            ->orderBy('tz.sortOrder', 'ASC')
            ->addOrderBy('tz.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les zones personnalisées d'une boutique.
     */
    public function countCustomByShop(Shop $shop): int
    {
        return (int) $this->createQueryBuilder('tz')
            ->select('COUNT(tz.id)')
            ->andWhere('tz.shop = :shop')
            ->andWhere('tz.isPreset = false')
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
