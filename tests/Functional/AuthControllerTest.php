<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->manager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    protected function tearDown(): void
    {
        if ($this->manager->isOpen()) {
            $connection = $this->manager->getConnection();
            try {
                $connection->executeStatement('TRUNCATE TABLE "user" CASCADE');
            } catch (\Exception $e) {
                // Ignore si table n'existe pas
            }
        }
        
        parent::tearDown();
    }

    public function testLoginWithValidCredentialsReturnsToken(): void
    {
        // Créer un utilisateur test
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstname('John');
        $user->setLastname('Doe');
        $user->setRoles(['ROLE_USER']);
        $user->setIsEmailVerified(true);
        $password = $this->passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($password);

        $this->manager->persist($user);
        $this->manager->flush();

        // Tenter login
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        
        self::assertArrayHasKey('token', $response);
        self::assertIsString($response['token']);
        self::assertNotEmpty($response['token']);
    }

    public function testLoginWithInvalidCredentialsReturnsUnauthorized(): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginWithWrongPasswordReturnsUnauthorized(): void
    {
        // Créer utilisateur
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setFirstname('Jane');
        $user->setLastname('Doe');
        $user->setRoles(['ROLE_USER']);
        $user->setIsEmailVerified(true);
        $password = $this->passwordHasher->hashPassword($user, 'correctpassword');
        $user->setPassword($password);

        $this->manager->persist($user);
        $this->manager->flush();

        // Tenter avec mauvais password
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginWithMissingEmailReturnsBadRequest(): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'password' => 'password123',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testLoginWithMissingPasswordReturnsBadRequest(): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testLoginWithEmptyJsonReturnsBadRequest(): void
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
