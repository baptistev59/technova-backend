<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Country>
 */
class CountryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    /**
     * @param string[] $codes
     * @return array<string, array{name: string, flag: string}>
     */
    public function getMapByCodes(array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        $rows = $this->createQueryBuilder('c')
            ->select('c.code, c.name, c.flag')
            ->where('c.code IN (:codes)')
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['code']] = [
                'name' => $row['name'],
                'flag' => $row['flag'],
            ];
        }

        return $map;
    }
}
