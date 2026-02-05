<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductVatRate;
use App\Entity\VatRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVatRate>
 */
class ProductVatRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVatRate::class);
    }

    /**
     * Trouver le taux TVA pour un produit dans un pays spécifique
     * 
     * @return ProductVatRate|null
     */
    public function findByProductAndCountry(Product $product, string $countryCode): ?ProductVatRate
    {
        return $this->createQueryBuilder('pvr')
            ->where('pvr.product = :product')
            ->andWhere('pvr.countryCode = :countryCode')
            ->andWhere('pvr.active = true')
            ->setParameter('product', $product)
            ->setParameter('countryCode', strtoupper($countryCode))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Lister tous les taux TVA d'un produit
     * 
     * @return ProductVatRate[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pvr')
            ->where('pvr.product = :product')
            ->andWhere('pvr.active = true')
            ->orderBy('pvr.countryCode', 'ASC')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();
    }

    /**
     * Lister les pays couverts par les taux TVA d'un produit
     * 
     * @return string[] (codes pays)
     */
    public function findCoveredCountries(Product $product): array
    {
        $results = $this->createQueryBuilder('pvr')
            ->select('DISTINCT pvr.countryCode')
            ->where('pvr.product = :product')
            ->andWhere('pvr.active = true')
            ->orderBy('pvr.countryCode', 'ASC')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();

        return array_map(fn($row) => $row['countryCode'], $results);
    }

    /**
     * Supprimer tous les taux TVA d'un produit
     */
    public function deleteByProduct(Product $product): int
    {
        return $this->createQueryBuilder('pvr')
            ->delete()
            ->where('pvr.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->executeStatement();
    }

    /**
     * Trouver les produits utilisant un taux TVA spécifique
     * (utile avant suppression d'un VatRate)
     * 
     * @return Product[]
     */
    public function findProductsByVatRate(VatRate $vatRate): array
    {
        return $this->createQueryBuilder('pvr')
            ->select('p')
            ->from(Product::class, 'p')
            ->where('pvr.vatRate = :vatRate')
            ->setParameter('vatRate', $vatRate)
            ->getQuery()
            ->getResult();
    }
}
