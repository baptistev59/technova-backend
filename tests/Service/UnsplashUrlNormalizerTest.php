<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UnsplashUrlNormalizer;
use PHPUnit\Framework\TestCase;

final class UnsplashUrlNormalizerTest extends TestCase
{
    public function testNormalizeReturnsNullForNonUnsplash(): void
    {
        $n = new UnsplashUrlNormalizer();
        $this->assertNull($n->normalize('https://example.com/image.jpg'));
    }

    public function testNormalizeExtractsIdAndReconstructs(): void
    {
        $n = new UnsplashUrlNormalizer();
        $res = $n->normalize('https://images.unsplash.com/photo-1517336714731-489689fd1ca8');
        $this->assertIsArray($res);
        $this->assertSame('unsplash', $res['source']);
        $this->assertStringContainsString('1517336714731-489689fd1ca8', $res['id']);
        $this->assertStringStartsWith('https://images.unsplash.com/photo-', $res['reconstructed']);
        $this->assertStringContainsString('auto=format', $res['reconstructed']);
    }
}
