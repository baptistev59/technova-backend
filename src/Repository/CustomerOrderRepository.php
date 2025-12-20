<?php

namespace App\Repository;

use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;

/**
 * @extends ServiceEntityRepository<CustomerOrder>
 */
class CustomerOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerOrder::class);
    }

    public function countSince(DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumPaidSince(DateTimeImmutable $since): float
    {
        $statuses = [CustomerOrder::STATUS_PAID, CustomerOrder::STATUS_SHIPPED];

        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->andWhere('o.createdAt >= :since')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('since', $since)
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return $this->countByStatuses([$status]);
    }

    public function countByStatuses(array $statuses): int
    {
        if ($statuses === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumTotalRevenue(): float
    {
        $paid = [CustomerOrder::STATUS_PAID, CustomerOrder::STATUS_SHIPPED];

        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', $paid)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<CustomerOrder>
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('owner')
            ->leftJoin('o.owner', 'owner')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByReference(string $reference): ?CustomerOrder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.reference = :reference')
            ->setParameter('reference', $reference)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array{
     *     orders: array<int, CustomerOrder>,
     *     total: int,
     *     pages: int,
     *     page: int,
     *     limit: int,
     *     revenue: float,
     *     statusCounts: array<string, int>
     * }
     */
    public function paginateForShop(Shop $shop, int $page = 1, int $limit = 10, ?string $statusFilter = null): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $baseQb = $this->createQueryBuilder('o')
            ->innerJoin('o.items', 'i')
            ->innerJoin(Product::class, 'p', Join::WITH, 'p.id = i.productId')
            ->andWhere('p.shop = :shop')
            ->setParameter('shop', $shop);

        $overallTotal = (int) (clone $baseQb)
            ->select('COUNT(DISTINCT o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $statusRows = (clone $baseQb)
            ->select('o.status AS status', 'COUNT(DISTINCT o.id) AS total')
            ->groupBy('o.status')
            ->getQuery()
            ->getScalarResult();

        $statusCounts = [];
        foreach ($statusRows as $row) {
            $statusCounts[(string) $row['status']] = (int) $row['total'];
        }

        $paidStatuses = [CustomerOrder::STATUS_PAID, CustomerOrder::STATUS_SHIPPED];
        $revenue = (float) ((clone $baseQb)
            ->andWhere('o.status IN (:paidStatuses)')
            ->setParameter('paidStatuses', $paidStatuses)
            ->select('COALESCE(SUM(i.lineTotal), 0) AS revenue')
            ->getQuery()
            ->getSingleScalarResult());

        $filteredQb = clone $baseQb;
        $allowedStatuses = [
            CustomerOrder::STATUS_PENDING,
            CustomerOrder::STATUS_PAID,
            CustomerOrder::STATUS_SHIPPED,
            CustomerOrder::STATUS_CANCELLED,
        ];

        if ($statusFilter !== null && !in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = null;
        }

        if ($statusFilter !== null) {
            $filteredQb
                ->andWhere('o.status = :statusFilter')
                ->setParameter('statusFilter', $statusFilter);
        }

        $total = (int) (clone $filteredQb)
            ->select('COUNT(DISTINCT o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $idRows = (clone $filteredQb)
            ->select('DISTINCT o.id AS id', 'o.createdAt AS createdAt')
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        $ids = array_map(static fn ($row) => (int) $row['id'], $idRows);

        $orders = [];
        if ($ids !== []) {
            $orders = $this->createQueryBuilder('o')
                ->addSelect('items')
                ->leftJoin('o.items', 'items')
                ->where('o.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('o.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        $pages = max(1, (int) ceil($total / $limit));

        return [
            'orders' => $orders,
            'total' => $total,
            'overallTotal' => $overallTotal,
            'pages' => $pages,
            'page' => $page,
            'limit' => $limit,
            'revenue' => $revenue,
            'statusCounts' => $statusCounts,
            'statusFilter' => $statusFilter,
        ];
    }

    /**
     * @return array<int, array{id: int, createdAt: DateTimeImmutable, total: float, status: string}>
     */
    public function findShopOrdersSince(Shop $shop, DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.id AS id', 'o.createdAt AS createdAt', 'o.status AS status', 'SUM(items.lineTotal) AS total')
            ->innerJoin('o.items', 'items')
            ->innerJoin(Product::class, 'p', Join::WITH, 'p.id = items.productId')
            ->andWhere('p.shop = :shop')
            ->andWhere('o.createdAt >= :since')
            ->groupBy('o.id')
            ->orderBy('o.createdAt', 'ASC')
            ->setParameter('shop', $shop)
            ->setParameter('since', $since)
            ->getQuery()
            ->getScalarResult();

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'createdAt' => new DateTimeImmutable($row['createdAt']),
                'total' => (float) $row['total'],
                'status' => (string) $row['status'],
            ];
        }, $rows);
    }

    public function findOneForShop(Shop $shop, int $orderId): ?CustomerOrder
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.items', 'items')
            ->innerJoin(Product::class, 'p', Join::WITH, 'p.id = items.productId')
            ->andWhere('o.id = :id')
            ->andWhere('p.shop = :shop')
            ->setParameter('id', $orderId)
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
