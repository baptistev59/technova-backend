<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VatRate;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VatRate>
 */
final class VatRateRepository extends ServiceEntityRepository implements VatRateRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VatRate::class);
    }

    public function findActiveByCountryCode(string $countryCode): ?VatRate
    {
        return $this->findOneBy([
            'countryCode' => strtoupper($countryCode),
            'active' => true,
        ]);
    }

    /**
     * Find effective rate using fallback: shop+country+code -> global shop=null -> null
     * Returns the first active matching VatRate or null.
     */
    public function findEffectiveRate(string $countryCode, ?Shop $shop = null, string $code = 'STANDARD'): ?VatRate
    {
        $countryCode = strtoupper($countryCode);
        $code = strtoupper($code);

        // 1) shop-specific
        if (null !== $shop) {
            $qb = $this->createQueryBuilder('v')
                ->andWhere('v.shop = :shop')
                ->andWhere('v.countryCode = :country')
                ->andWhere('v.code = :code')
                ->andWhere('v.active = true')
                ->setParameters(['shop' => $shop, 'country' => $countryCode, 'code' => $code])
                ->setMaxResults(1);

            $res = $qb->getQuery()->getOneOrNullResult();
            if (null !== $res) {
                return $res;
            }
        }

        // 2) global (shop = null)
        return $this->createQueryBuilder('v')
            ->andWhere('v.shop IS NULL')
            ->andWhere('v.countryCode = :country')
            ->andWhere('v.code = :code')
            ->andWhere('v.active = true')
            ->setParameters(['country' => $countryCode, 'code' => $code])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
