<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustomerOrder;
use App\Entity\ReturnRequest;
use App\Entity\User;
use App\Entity\Vendor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReturnRequest>
 */
final class ReturnRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReturnRequest::class);
    }

    /**
     * @return list<ReturnRequest>
     */
    public function findForVendor(Vendor $vendor): array
    {
        $shopIds = array_map(
            static fn ($shop) => $shop->getId(),
            $vendor->getShops()->toArray()
        );

        if ([] === $shopIds) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('o', 'requester')
            ->innerJoin('r.order', 'o')
            ->innerJoin('o.items', 'item')
            ->leftJoin('r.requester', 'requester')
            ->andWhere('item.shopId IN (:shopIds)')
            ->setParameter('shopIds', $shopIds)
            ->groupBy('r.id', 'o.id', 'requester.id')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForVendor(Vendor $vendor, int $returnId): ?ReturnRequest
    {
        $shopIds = array_map(
            static fn ($shop) => $shop->getId(),
            $vendor->getShops()->toArray()
        );

        if ([] === $shopIds) {
            return null;
        }

        return $this->createQueryBuilder('r')
            ->addSelect('o', 'requester')
            ->innerJoin('r.order', 'o')
            ->innerJoin('o.items', 'item')
            ->leftJoin('r.requester', 'requester')
            ->andWhere('r.id = :id')
            ->andWhere('item.shopId IN (:shopIds)')
            ->setParameter('id', $returnId)
            ->setParameter('shopIds', $shopIds)
            ->groupBy('r.id', 'o.id', 'requester.id')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForOrderAndUser(CustomerOrder $order, User $user): ?ReturnRequest
    {
        return $this->findOneBy([
            'order' => $order,
            'requester' => $user,
        ]);
    }
}
