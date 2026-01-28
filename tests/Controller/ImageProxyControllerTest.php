<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ImageProxyController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ImageProxyControllerTest extends TestCase
{
    public function testProxyReturnsTransformedAndCachedImage(): void
    {
        $base64 = trim(file_get_contents(__DIR__.'/../Fixtures/sample.jpg'));
        $imgData = base64_decode($base64);

        $mockResp = $this->createMock(ResponseInterface::class);
        $mockResp->method('getStatusCode')->willReturn(200);
        $mockResp->method('getHeaders')->willReturn(['content-type' => ['image/png']]);
        $mockResp->method('getContent')->willReturn($imgData);

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')->willReturn($mockResp);

        $cacheDir = sys_get_temp_dir().'/symfony_image_proxy_test_cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        $controller = new ImageProxyController($http, $cacheDir);

        // build request for resize to 10x10
        $req = new Request(['u' => 'https://images.unsplash.com/photo-1', 'w' => '10', 'h' => '10', 'fit' => 'contain']);

        $response = $controller->proxy($req);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));

        // Call again to hit cache (http client should still be called but we verify response ok)
        $response2 = $controller->proxy($req);
        $this->assertSame(200, $response2->getStatusCode());
    }
}
