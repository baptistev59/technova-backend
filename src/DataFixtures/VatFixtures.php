<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VatRate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class VatFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $rates = [
            // France
            ['FR', 'STANDARD', 20.0],
            ['FR', 'REDUCED', 5.5],
            ['FR', 'ZERO', 0.0],
            // Germany
            ['DE', 'STANDARD', 19.0],
            ['DE', 'REDUCED', 7.0],
            ['DE', 'ZERO', 0.0],
            // Spain
            ['ES', 'STANDARD', 21.0],
            ['ES', 'REDUCED', 10.0],
            ['ES', 'ZERO', 4.0],
        ];

        foreach ($rates as [$country, $code, $rate]) {
            $vat = new VatRate($country, (float) $rate, $code);
            $vat->setLabel(sprintf('%s %s', $code, $country));
            $vat->setIsDefault('STANDARD' === $code);
            $manager->persist($vat);
        }

        $manager->flush();
    }
}
