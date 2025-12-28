<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\Shop;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerOrder>
 */
class CustomerOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerOrder::class);
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSinceWithStatuses(\DateTimeImmutable $since, array $statuses): int
    {
        $statusValues = $this->normalizeStatuses($statuses);

        if ([] === $statusValues) {
            return 0;
        }

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :since')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('since', $since)
            ->setParameter('statuses', $statusValues)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumPaidSince(\DateTimeImmutable $since): float
    {
        $statuses = [OrderStatus::Paid, OrderStatus::Shipped];

        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->andWhere('o.createdAt >= :since')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('since', $since)
            ->setParameter('statuses', $this->normalizeStatuses($statuses))
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

    public function countByStatus(OrderStatus|string $status): int
    {
        return $this->countByStatuses([$status]);
    }

    public function countByStatuses(array $statuses): int
    {
        $statusValues = $this->normalizeStatuses($statuses);

        if ([] === $statusValues) {
            return 0;
        }

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', $statusValues)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumTotalRevenue(): float
    {
        $paid = [OrderStatus::Paid, OrderStatus::Shipped];

        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', $this->normalizeStatuses($paid))
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
     *     overallTotal: int,
     *     pages: int,
     *     page: int,
     *     limit: int,
     *     revenue: float,
     *     statusCounts: array<string, int>,
     *     statusFilter: string|null
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

        $paidStatuses = [OrderStatus::Paid, OrderStatus::Shipped];
        $revenue = (float) (clone $baseQb)
            ->andWhere('o.status IN (:paidStatuses)')
            ->setParameter('paidStatuses', $this->normalizeStatuses($paidStatuses))
            ->select('COALESCE(SUM(i.lineTotal), 0) AS revenue')
            ->getQuery()
            ->getSingleScalarResult();

        $filteredQb = clone $baseQb;
        $allowedStatuses = array_map(
            static fn (OrderStatus $status): string => $status->value,
            OrderStatus::cases()
        );

        if (null !== $statusFilter && !in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = null;
        }

        if (null !== $statusFilter) {
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
        if ([] !== $ids) {
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
     * @return array<int, array{id: int, createdAt: \DateTimeImmutable, total: float, status: string}>
     */
    public function findShopOrdersSince(Shop $shop, \DateTimeImmutable $since): array
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
                'createdAt' => new \DateTimeImmutable($row['createdAt']),
                'total' => (float) $row['total'],
                'status' => (string) $row['status'],
            ];
        }, $rows);
    }

    public function findOneForShop(Shop $shop, int $orderId): ?CustomerOrder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('items', 'owner')
            ->innerJoin('o.items', 'items')
            ->leftJoin('o.owner', 'owner')
            ->innerJoin(Product::class, 'p', Join::WITH, 'p.id = items.productId')
            ->andWhere('o.id = :id')
            ->andWhere('p.shop = :shop')
            ->setParameter('id', $orderId)
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, array{day: \DateTimeImmutable, total: int}>
     */
    public function findDailyOrderCountsSince(\DateTimeImmutable $since): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT DATE_TRUNC('day', created_at) AS day, COUNT(id) AS total
            FROM customer_order
            WHERE created_at >= :since
            GROUP BY day
            ORDER BY day ASC",
            ['since' => $since],
            ['since' => 'datetime_immutable']
        );

        return array_map(static function (array $row): array {
            return [
                'day' => new \DateTimeImmutable((string) $row['day']),
                'total' => (int) $row['total'],
            ];
        }, $rows);
    }

    /**
     * @param array<int, OrderStatus|string> $statuses
     *
     * @return array<int, array{month: \DateTimeImmutable, total: float}>
     */
    public function findMonthlyRevenueSince(\DateTimeImmutable $since, array $statuses): array
    {
        $statusValues = $this->normalizeStatuses($statuses);

        if ([] === $statusValues) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT DATE_TRUNC('month', created_at) AS month, COALESCE(SUM(total_amount), 0) AS total
            FROM customer_order
            WHERE created_at >= :since
            AND status IN (:statuses)
            GROUP BY month
            ORDER BY month ASC",
            ['since' => $since, 'statuses' => $statusValues],
            ['since' => 'datetime_immutable', 'statuses' => ArrayParameterType::STRING]
        );

        return array_map(static function (array $row): array {
            return [
                'month' => new \DateTimeImmutable((string) $row['month']),
                'total' => (float) $row['total'],
            ];
        }, $rows);
    }

    /**
     * @param array<int, OrderStatus|string> $statuses
     *
     * @return array<int, string>
     */
    private function normalizeStatuses(array $statuses): array
    {
        return array_values(array_filter(array_map(
            static fn (OrderStatus|string $status): string => $status instanceof OrderStatus ? $status->value : $status,
            $statuses
        )));
    }
}
