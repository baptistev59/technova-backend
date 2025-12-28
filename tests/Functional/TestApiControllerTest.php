<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestApiControllerTest extends WebTestCase
{
    public function testHealthcheckEndpointReturnsOk(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test');

        self::assertResponseIsSuccessful();
        self::assertTrue(
            $client->getResponse()->headers->contains('content-type', 'application/json'),
            'Response should be JSON'
        );

        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('ok', $data['status']);
        self::assertStringContainsString('TechNova', $data['message']);
    }
}
