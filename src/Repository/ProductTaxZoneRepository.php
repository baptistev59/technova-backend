<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductTaxZone;
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
     * Find all tax zone configurations for a specific product.
     * 
     * @return ProductTaxZone[]
     */
    public function findForProduct(Product $product): array
    {
        return $this->createQueryBuilder('ptz')
            ->andWhere('ptz.product = :product')
            ->setParameter('product', $product)
            ->orderBy('ptz.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tax zone configuration for product in a specific country.
     * Returns the first matching configuration that includes the country code.
     */
    public function findForProductAndCountry(Product $product, string $countryCode): ?ProductTaxZone
    {
        $productId = $product->getId();
        if (null === $productId) {
            return null;
        }

        $countryCode = strtoupper($countryCode);

        // PostgreSQL JSONB query: check if country_codes array contains the country
        $sql = <<<'SQL'
            SELECT ptz.id
            FROM product_tax_zone ptz
            WHERE ptz.product_id = :productId
              AND ptz.country_codes @> to_jsonb(:country::text)
            LIMIT 1
            SQL;

        try {
            $id = $this->getEntityManager()
                ->getConnection()
                ->fetchOne($sql, [
                    'productId' => $productId,
                    'country' => $countryCode,
                ]);

            if (!$id) {
                return null;
            }

            return $this->find((int) $id);
        } catch (\Exception $e) {
            // Fallback: in-memory checking if JSON query fails
            $zones = $this->findForProduct($product);
            foreach ($zones as $zone) {
                if ($zone->hasCountry($countryCode)) {
                    return $zone;
                }
            }

            return null;
        }
    }

    /**
     * Get all countries covered by product tax zone configurations.
     * 
     * @return string[] Array of country codes
     */
    public function getCoveredCountries(Product $product): array
    {
        $zones = $this->findForProduct($product);
        $countries = [];
        
        foreach ($zones as $zone) {
            $countries = array_merge($countries, $zone->getCountryCodes());
        }
        
        return array_unique($countries);
    }
}

