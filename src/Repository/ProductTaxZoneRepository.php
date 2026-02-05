<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductTaxZone;
use App\Entity\TaxZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductTaxZone>
 */
class ProductTaxZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductTaxZone::class);
    }

    /**
     * Find all tax zones for a specific product
     */
    public function findForProduct(Product $product): array
    {
        return $this->createQueryBuilder('ptz')
            ->andWhere('ptz.product = :product')
            ->setParameter('product', $product)
            ->leftJoin('ptz.taxZone', 'tz')
            ->addSelect('tz')
            ->orderBy('tz.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tax zone for product in a specific country
     */
    public function findForProductAndCountry(Product $product, string $countryCode): ?ProductTaxZone
    {
        $productId = $product->getId();
        if (null === $productId) {
            return null;
        }

        $sql = <<<'SQL'
            SELECT ptz.id
            FROM product_tax_zone ptz
            INNER JOIN tax_zone tz ON tz.id = ptz.tax_zone_id
            WHERE ptz.product_id = :productId
              AND tz.country_codes @> to_jsonb(:country::text)
            LIMIT 1
            SQL;

        try {
            $id = $this->getEntityManager()
                ->getConnection()
                ->fetchOne($sql, [
                    'productId' => $productId,
                    'country' => strtoupper($countryCode),
                ]);

            if (!$id) {
                return null;
            }

            return $this->find((int) $id);
        } catch (\Exception $e) {
            // If the raw query fails, fall back to in-memory checking
            $zones = $this->findForProduct($product);
            foreach ($zones as $zone) {
                $codes = $zone->getTaxZone()?->getCountryCodes() ?? [];
                if (in_array(strtoupper($countryCode), $codes, true)) {
                    return $zone;
                }
            }

            return null;
        }
    }

    /**
     * Get tax class for product in a specific zone
     */
    public function getTaxClassForZone(Product $product, TaxZone $zone): ?string
    {
        $result = $this->createQueryBuilder('ptz')
            ->select('ptz.taxClass')
            ->andWhere('ptz.product = :product')
            ->andWhere('ptz.taxZone = :zone')
            ->setParameter('product', $product)
            ->setParameter('zone', $zone)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['taxClass'] ?? null;
    }
}
