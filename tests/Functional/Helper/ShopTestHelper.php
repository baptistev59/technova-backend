<?php

namespace App\Tests\Functional\Helper;

use App\Entity\Shop;
use App\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;

trait ShopTestHelper
{
    protected function createShopForVendor(
        EntityManagerInterface $em,
        Vendor $vendor
    ): Shop {
        $shop = new Shop();
        $emailToken = bin2hex(random_bytes(3));
        $shop
            ->setName('Test Shop ' . bin2hex(random_bytes(3)))
            ->setSlug('test-shop-' . bin2hex(random_bytes(3)))
            ->setContactEmail(sprintf('shop-tests-%s@technova.test', $emailToken))
            ->setOwner($vendor);

        $em->persist($shop);
        $em->flush();

        return $shop;
    }
}
