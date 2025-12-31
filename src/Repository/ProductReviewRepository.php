<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductReview;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Utile pour aplatir les avis dans l'API si besoin (count, moyenne, etc.).
 *
 * @extends ServiceEntityRepository<ProductReview>
 */
class ProductReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReview::class);
    }

    /**
     * @param array<int> $productIds
     *
     * @return list<ProductReview>
     */
    public function findForAuthorAndProducts(User $author, array $productIds): array
    {
        if ([] === $productIds) {
            return [];
        }

        return $this->createQueryBuilder('review')
            ->andWhere('review.author = :author')
            ->andWhere('review.product IN (:products)')
            ->setParameter('author', $author)
            ->setParameter('products', $productIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int> $productIds
     *
     * @return array<int, array{avg: float, count: int}>
     */
    public function getSummariesForProducts(array $productIds): array
    {
        if ([] === $productIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('review')
            ->select('IDENTITY(review.product) AS product_id')
            ->addSelect('AVG(review.rating) AS avg_rating')
            ->addSelect('COUNT(review.id) AS review_count')
            ->andWhere('review.product IN (:products)')
            ->andWhere('review.approved = true')
            ->setParameter('products', $productIds)
            ->groupBy('review.product')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $summaries[$productId] = [
                'avg' => round((float) $row['avg_rating'], 1),
                'count' => (int) $row['review_count'],
            ];
        }

        return $summaries;
    }

    /**
     * @param array<int> $shopIds
     *
     * @return array<int, array{avg: float, count: int}>
     */
    public function getSummariesForShops(array $shopIds): array
    {
        if ([] === $shopIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('review')
            ->select('IDENTITY(product.shop) AS shop_id')
            ->addSelect('AVG(review.rating) AS avg_rating')
            ->addSelect('COUNT(review.id) AS review_count')
            ->innerJoin('review.product', 'product')
            ->andWhere('product.shop IN (:shops)')
            ->andWhere('review.approved = true')
            ->setParameter('shops', $shopIds)
            ->groupBy('product.shop')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];
        foreach ($rows as $row) {
            $shopId = (int) $row['shop_id'];
            $summaries[$shopId] = [
                'avg' => round((float) $row['avg_rating'], 1),
                'count' => (int) $row['review_count'],
            ];
        }

        return $summaries;
    }

    /**
     * @return list<ProductReview>
     */
    public function findApprovedForProduct(int $productId): array
    {
        return $this->createQueryBuilder('review')
            ->andWhere('review.product = :product')
            ->andWhere('review.approved = true')
            ->setParameter('product', $productId)
            ->orderBy('review.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{count: int, avg: float}
     */
    public function getApprovedStatsForProduct(int $productId): array
    {
        $row = $this->createQueryBuilder('review')
            ->select('COUNT(review.id) AS review_count')
            ->addSelect('COALESCE(AVG(review.rating), 0) AS avg_rating')
            ->andWhere('review.product = :product')
            ->andWhere('review.approved = true')
            ->setParameter('product', $productId)
            ->getQuery()
            ->getSingleResult();

        return [
            'count' => (int) $row['review_count'],
            'avg' => round((float) $row['avg_rating'], 1),
        ];
    }
}
