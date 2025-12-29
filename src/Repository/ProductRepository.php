<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Retourne une suggestion de noms de produits pour l'autocomplétion.
     *
     * @return string[]
     */
    public function findNameSuggestions(int $limit = 30): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.name AS name')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * Retourne les noms de produits qui contiennent la chaîne fournie.
     *
     * @return string[]
     */
    public function findNamesContaining(string $query, int $limit = 40): array
    {
        if ('' === $query) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p.name AS name, p.keywords AS keywords')
            ->andWhere('(LOWER(p.name) LIKE :query OR LOWER(p.keywords) LIKE :query)')
            ->andWhere('p.isPublished = :published')
            ->setParameter('query', '%'.mb_strtolower($query).'%')
            ->setParameter('published', true)
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit);

        $rows = $qb->getQuery()->getScalarResult();

        $suggestions = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ('' !== $name) {
                $suggestions[] = $name;
            }

            $keywords = (string) ($row['keywords'] ?? '');
            if ('' !== $keywords) {
                $tokens = preg_split('/[\s,;]+/', $keywords);
                foreach ($tokens as $token) {
                    $token = trim($token);
                    if ('' !== $token) {
                        $suggestions[] = $token;
                    }
                }
            }
        }

        $queryNormalized = mb_strtolower($query);
        $unique = array_values(array_unique(array_filter($suggestions, static fn (string $value) => '' !== $value && str_contains(mb_strtolower($value), $queryNormalized)), SORT_STRING));

        return array_slice($unique, 0, $limit);
    }

    /**
     * Retourne les derniers produits publiés pour une boutique donnée.
     *
     * @return Product[]
     */
    public function findLatestPublishedForShop(Shop $shop, int $limit = 10): array
    {
        $ids = $this->fetchShopProductIds(
            $shop,
            ['p.createdAt' => 'DESC'],
            $limit,
            [
                ['field' => 'p.isPublished', 'value' => true],
            ]
        );

        return $this->loadProductsByIdsWithRelations($ids);
    }

    public function findFeaturedPublishedForShop(Shop $shop, int $limit = 10): array
    {
        $ids = $this->fetchShopProductIds(
            $shop,
            ['p.updatedAt' => 'DESC'],
            $limit,
            [
                ['field' => 'p.isPublished', 'value' => true],
                ['field' => 'p.isFeatured', 'value' => true],
            ]
        );

        return $this->loadProductsByIdsWithRelations($ids);
    }

    private function fetchShopProductIds(Shop $shop, array $orderings, int $limit, array $conditions = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->andWhere('p.shop = :shop')
            ->setParameter('shop', $shop);

        foreach ($conditions as $index => $condition) {
            $paramKey = 'cond_'.$index;
            $qb->andWhere($condition['field'].' = :'.$paramKey)
               ->setParameter($paramKey, $condition['value']);
        }

        foreach ($orderings as $field => $direction) {
            $qb->addOrderBy($field, $direction);
        }

        $qb->setMaxResults($limit);

        $result = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $result);
    }

    private function loadProductsByIdsWithRelations(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p')
            ->leftJoin('p.images', 'img')
            ->addSelect('img')
            ->leftJoin('p.brand', 'b')
            ->addSelect('b')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids);

        // CASE pour l’ordre
        $case = 'CASE';
        foreach ($ids as $index => $id) {
            $param = 'order_'.$index;
            $case .= ' WHEN p.id = :'.$param.' THEN '.$index;
            $qb->setParameter($param, $id);
        }
        $case .= ' ELSE '.count($ids).' END';

        // 👇 OBLIGATOIRE pour PostgreSQL
        $qb
            ->addSelect($case.' AS HIDDEN sort_order')
            ->addOrderBy('sort_order', 'ASC');

        return $qb->getQuery()->getResult();
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
     *     shop?: Shop|null,
     *     minPrice?: float|null,
     *     maxPrice?: float|null,
     *     search?: string|null,
     *     sort?: string|null
     * } $filters
     */
    private function createFilterQueryBuilder(array $filters, ?bool &$hasSearchOrdering = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.brand', 'b')
            ->addSelect('b')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true);

        $hasSearchOrdering = false;
        $orderMethod = 'orderBy';

        if (!empty($filters['category'])) {
            $qb->andWhere('c.slug = :category')
                ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['brand'])) {
            $qb->andWhere('b.slug = :brand')
                ->setParameter('brand', $filters['brand']);
        }

        if (!empty($filters['shop']) && $filters['shop'] instanceof Shop) {
            $qb->andWhere('p.shop = :shopFilter')
                ->setParameter('shopFilter', $filters['shop']);
        }

        $minPrice = $filters['minPrice'] ?? null;
        if (null !== $minPrice) {
            $qb->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', (float) $minPrice);
        }

        $maxPrice = $filters['maxPrice'] ?? null;
        if (null !== $maxPrice) {
            $qb->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', (float) $maxPrice);
        }

        if (!empty($filters['search'])) {
            $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $filters['search'])));
            if ('' !== $normalized) {
                $terms = array_values(array_filter(explode(' ', $normalized)));
                if ([] !== $terms) {
                    $scoreParts = [];

                    foreach ($terms as $index => $term) {
                        $param = 'term_'.$index;
                        $condition = sprintf(
                            '(LOWER(p.name) LIKE :%1$s OR LOWER(p.shortDescription) LIKE :%1$s OR LOWER(p.keywords) LIKE :%1$s)',
                            $param
                        );
                        $scoreParts[] = sprintf('CASE WHEN %s THEN 1 ELSE 0 END', $condition);
                        $qb->andWhere($condition);
                        $qb->setParameter($param, '%'.$term.'%');
                    }

                    if ([] !== $scoreParts) {
                        $qb->addSelect('('.implode(' + ', $scoreParts).') AS HIDDEN relevance');
                        $qb->addOrderBy('relevance', 'DESC');
                        $hasSearchOrdering = true;
                        $orderMethod = 'addOrderBy';
                    }
                }
            }
        }

        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'price_asc' => $qb->{$orderMethod}('p.price', 'ASC'),
            'price_desc' => $qb->{$orderMethod}('p.price', 'DESC'),
            'oldest' => $qb->{$orderMethod}('p.createdAt', 'ASC'),
            default => $qb->{$orderMethod}('p.createdAt', 'DESC'),
        };

        return $qb;
    }

    public function filterBy(array $filters): array
    {
        $qb = $this->createFilterQueryBuilder($filters, $hasSearchOrdering);

        return $qb->getQuery()->getResult();
    }

    public function filterByPaginated(array $filters, int $page, int $limit): array
    {
        $qb = $this->createFilterQueryBuilder($filters, $hasSearchOrdering);
        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(DISTINCT p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $perPage = max(1, $limit);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return [
            'items' => $qb->getQuery()->getResult(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
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
     * @return array{items: list<Product>, total: int}
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
            $qb->setParameter('lowStockThreshold', 10)
                ->setParameter('groupedType', 'grouped');
            match ($filters['stock']) {
                'out_of_stock' => $qb->andWhere('p.type != :groupedType')->andWhere('p.stock <= 0'),
                'low_stock' => $qb->andWhere('p.type != :groupedType')
                    ->andWhere('p.stock > 0')
                    ->andWhere('p.stock <= COALESCE(p.lowStockThreshold, :lowStockThreshold)'),
                'in_stock' => $qb->andWhere('p.type != :groupedType')
                    ->andWhere('p.stock > COALESCE(p.lowStockThreshold, :lowStockThreshold)'),
                default => null,
            };
        }

        if (isset($filters['status']) && '' !== $filters['status']) {
            $qb->andWhere('p.isPublished = :publishedStatus')
                ->setParameter('publishedStatus', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $filters['search'])));
            if ('' !== $normalized) {
                $terms = array_values(array_filter(explode(' ', $normalized)));
                if ([] !== $terms) {
                    $orExpressions = [];
                    foreach ($terms as $index => $term) {
                        $param = 'vendor_search_'.$index;
                        $orExpressions[] = sprintf(
                            '(LOWER(p.name) LIKE :%1$s OR LOWER(p.sku) LIKE :%1$s OR LOWER(p.description) LIKE :%1$s)',
                            $param
                        );
                        $qb->setParameter($param, '%'.$term.'%');
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

    public function countLowStockForShop(Shop $shop, int $threshold): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.shop = :shop')
            ->andWhere('p.type != :grouped')
            ->andWhere('p.stock <= COALESCE(p.lowStockThreshold, :threshold)')
            ->setParameter('shop', $shop)
            ->setParameter('grouped', 'grouped')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
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

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
