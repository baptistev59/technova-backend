<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\VatRate;
use App\Repository\VatRateRepositoryInterface;
use App\Service\VatCalculator;
use PHPUnit\Framework\TestCase;

class VatCalculatorTest extends TestCase
{
    public function testCalculateUsingRepositoryRate(): void
    {
        $repo = $this->createMock(VatRateRepositoryInterface::class);
        $repo->method('findEffectiveRate')->willReturn(new VatRate('FR', 20.0, 'STANDARD'));

        $calc = new VatCalculator($repo);

        $this->assertSame(20.0, $calc->getRatePercent('FR'));
        $this->assertSame(20.00, $calc->calculateTaxFromNet(100.00, 'FR'));
        $this->assertSame(120.00, $calc->calculateGrossFromNet(100.00, 'FR'));
        $this->assertSame(100.00, $calc->calculateNetFromGross(120.00, 'FR'));

        // rounding check: 9.99 * 20% => tax = 2.00 with cents rounding
        $this->assertSame(2.00, $calc->calculateTaxFromNet(9.99, 'FR'));
    }

    public function testFallbackToDefaultRateWhenRepositoryReturnsNull(): void
    {
        $repo = $this->createMock(VatRateRepositoryInterface::class);
        $repo->method('findEffectiveRate')->willReturn(null);

        $calc = new VatCalculator($repo, 18.0);

        $this->assertSame(18.0, $calc->getRatePercent('FR'));
        $this->assertSame(18.00, $calc->calculateTaxFromNet(100.00, 'FR'));
    }
}
