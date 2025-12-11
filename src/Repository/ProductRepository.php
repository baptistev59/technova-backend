<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Retourne les derniers produits publiés pour alimenter la home/sections "nouveautés".
     *
     * @return Product[]
     */
    public function findLatestPublished(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sélectionne les produits mis à la une.
     *
     * @return Product[]
     */
    public function findFeaturedPublished(int $limit = 3): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isFeatured = :featured')
            ->setParameter('published', true)
            ->setParameter('featured', true)
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtrage partagé entre l'API et les pages Twig (catégorie + marque).
     *
     * @param array{
     *     category?: string|null,
     *     brand?: string|null,
     *     minPrice?: float|null,
     *     maxPrice?: float|null,
     *     search?: string|null,
     *     sort?: string|null
     * } $filters
     * @return Product[]
     */
    public function filterBy(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.brand', 'b')
            ->addSelect('b')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.createdAt', 'DESC');
        $hasSearchOrdering = false;

        if (!empty($filters['category'])) {
            $qb->andWhere('c.slug = :category')
                ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['brand'])) {
            $qb->andWhere('b.slug = :brand')
                ->setParameter('brand', $filters['brand']);
        }

        if (isset($filters['minPrice']) && is_numeric($filters['minPrice'])) {
            $qb->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', (float) $filters['minPrice']);
        }

        if (isset($filters['maxPrice']) && is_numeric($filters['maxPrice'])) {
            $qb->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', (float) $filters['maxPrice']);
        }

        if (!empty($filters['search'])) {
            $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $filters['search'])));
            if ($normalized !== '') {
                $terms = array_values(array_filter(explode(' ', $normalized)));
                if ($terms !== []) {
                    $orExpressions = [];
                    $scoreParts = [];

                    foreach ($terms as $index => $term) {
                        $param = 'term_' . $index;
                        $condition = sprintf(
                            '(LOWER(p.name) LIKE :%1$s OR LOWER(p.shortDescription) LIKE :%1$s)',
                            $param
                        );
                        $orExpressions[] = $condition;
                        $scoreParts[] = sprintf('CASE WHEN %s THEN 1 ELSE 0 END', $condition);
                        $qb->setParameter($param, '%' . $term . '%');
                    }

                    $qb->andWhere(implode(' OR ', $orExpressions));
                    $qb->addSelect('(' . implode(' + ', $scoreParts) . ') AS HIDDEN relevance');
                    $qb->addOrderBy('relevance', 'DESC');
                    $hasSearchOrdering = true;
                }
            }
        }

        $sort = $filters['sort'] ?? 'newest';
        $orderMethod = $hasSearchOrdering ? 'addOrderBy' : 'orderBy';
        match ($sort) {
            'price_asc' => $qb->{$orderMethod}('p.price', 'ASC'),
            'price_desc' => $qb->{$orderMethod}('p.price', 'DESC'),
            'oldest' => $qb->{$orderMethod}('p.createdAt', 'ASC'),
            default => $qb->{$orderMethod}('p.createdAt', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Filtrage dédié aux vendeurs dans l'espace "Mes produits".
     *
     * @param array{
     *     search?: string|null,
     *     category?: string|null,
     *     brand?: string|null,
     *     stock?: string|null,
     *     type?: string|null,
     *     status?: string|null
     * } $filters
     *
     * @return Product[]
     */
    public function filterForVendor(Shop $shop, array $filters = [], int $page = 1, int $limit = 15, string $sort = 'updated_desc'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.brand', 'b')
            ->addSelect('b')
            ->leftJoin('p.images', 'img')
            ->addSelect('img')
            ->andWhere('p.shop = :shop')
            ->setParameter('shop', $shop)
            ->distinct();

        if (!empty($filters['category'])) {
            $qb->andWhere('c.slug = :categorySlug')
                ->setParameter('categorySlug', $filters['category']);
        }

        if (!empty($filters['brand'])) {
            $qb->andWhere('b.slug = :brandSlug')
                ->setParameter('brandSlug', $filters['brand']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('p.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['stock'])) {
            match ($filters['stock']) {
                'out_of_stock' => $qb->andWhere('p.stock <= 0'),
                'low_stock' => $qb->andWhere('p.stock > 0')->andWhere('p.stock <= 10'),
                'in_stock' => $qb->andWhere('p.stock > 10'),
                default => null,
            };
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $qb->andWhere('p.isPublished = :publishedStatus')
                ->setParameter('publishedStatus', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $filters['search'])));
            if ($normalized !== '') {
                $terms = array_values(array_filter(explode(' ', $normalized)));
                if ($terms !== []) {
                    $orExpressions = [];
                    foreach ($terms as $index => $term) {
                        $param = 'vendor_search_' . $index;
                        $orExpressions[] = sprintf(
                            '(LOWER(p.name) LIKE :%1$s OR LOWER(p.sku) LIKE :%1$s OR LOWER(p.description) LIKE :%1$s)',
                            $param
                        );
                        $qb->setParameter($param, '%' . $term . '%');
                    }
                    $qb->andWhere(implode(' OR ', $orExpressions));
                }
            }
        }

        $sort = $sort ?: 'updated_desc';
        match ($sort) {
            'price_asc' => $qb->orderBy('p.price', 'ASC'),
            'price_desc' => $qb->orderBy('p.price', 'DESC'),
            'name_asc' => $qb->orderBy('p.name', 'ASC'),
            'name_desc' => $qb->orderBy('p.name', 'DESC'),
            'updated_asc' => $qb->orderBy('p.updatedAt', 'ASC'),
            default => $qb->orderBy('p.updatedAt', 'DESC'),
        };

        $page = max(1, $page);
        $limit = max(1, $limit);
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb, true);

        return [
            'items' => iterator_to_array($paginator, false),
            'total' => count($paginator),
        ];
    }

    public function findPreviousProduct(Product $product, ?Shop $shopScope = null): ?Product
    {
        return $this->createAdjacentQuery($product, $shopScope, true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextProduct(Product $product, ?Shop $shopScope = null): ?Product
    {
        return $this->createAdjacentQuery($product, $shopScope, false)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createAdjacentQuery(Product $product, ?Shop $shopScope, bool $isPrevious)
    {
        $qb = $this->createQueryBuilder('p')
            ->setMaxResults(1)
            ->setParameter('updatedAt', $product->getUpdatedAt())
            ->setParameter('currentId', $product->getId());

        if ($shopScope) {
            $qb->andWhere('p.shop = :shop')
                ->setParameter('shop', $shopScope);
        } else {
            $qb->andWhere('p.isPublished = :published')
                ->setParameter('published', true);
        }

        if ($isPrevious) {
            $qb->andWhere('(p.updatedAt > :updatedAt) OR (p.updatedAt = :updatedAt AND p.id > :currentId)')
                ->orderBy('p.updatedAt', 'ASC')
                ->addOrderBy('p.id', 'ASC');
        } else {
            $qb->andWhere('(p.updatedAt < :updatedAt) OR (p.updatedAt = :updatedAt AND p.id < :currentId)')
                ->orderBy('p.updatedAt', 'DESC')
                ->addOrderBy('p.id', 'DESC');
        }

        return $qb;
    }
}
