<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Référentiel pour les boutiques. Pour l’instant on ne fait que findOne/findAll.
 *
 * @extends ServiceEntityRepository<Shop>
 */
class ShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shop::class);
    }

    /**
     * Retourne une pagination simple des boutiques publiques.
     *
     * @return array{
     *     items: Shop[],
     *     total: int,
     *     page: int,
     *     pages: int,
     *     per_page: int
     * }
     */
    public function paginate(int $page, int $limit, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, $limit);

        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.owner', 'vendor')
            ->addSelect('vendor')
            ->leftJoin('vendor.owner', 'vendorUser')
            ->addSelect('vendorUser')
            ->orderBy('s.updatedAt', 'DESC');

        if (!empty($filters['search'])) {
            $qb
                ->andWhere('LOWER(s.name) LIKE :term OR LOWER(vendor.companyName) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower($filters['search']).'%');
        }

        if (!empty($filters['vendor'])) {
            $term = '%'.mb_strtolower($filters['vendor']).'%';
            $qb
                ->andWhere('LOWER(vendor.companyName) LIKE :vendorTerm OR LOWER(vendorUser.firstname) LIKE :vendorTerm OR LOWER(vendorUser.lastname) LIKE :vendorTerm OR LOWER(CONCAT(vendorUser.firstname, \' \', vendorUser.lastname)) LIKE :vendorTerm')
                ->setParameter('vendorTerm', $term);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * Charge les boutiques identifiées et leurs vendeurs pour les statuts admin.
     *
     * @param int[] $shopIds
     *
     * @return Shop[]
     */
    public function findWithVendorByIds(array $shopIds): array
    {
        if (empty($shopIds)) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->leftJoin('s.owner', 'vendor')
            ->addSelect('vendor')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', $shopIds)
            ->getQuery()
            ->getResult();
    }
}
