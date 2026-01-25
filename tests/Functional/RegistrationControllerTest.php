<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private UserRepository $userRepository;
    private static int $testCounter = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->manager = $container->get(EntityManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        
        // Incrémenter le compteur pour simuler une IP différente à chaque test
        self::$testCounter++;
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

    public function testRegisterWithValidDataCreatesUserAndReturnsToken(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        
        self::assertArrayHasKey('token', $response);
        self::assertArrayHasKey('user', $response);
        self::assertIsString($response['token']);
        self::assertNotEmpty($response['token']);
        
        // Vérifier que l'utilisateur existe en base
        $user = $this->userRepository->findOneBy(['email' => 'newuser@example.com']);
        self::assertNotNull($user);
        self::assertSame('John', $user->getFirstname());
        self::assertSame('Doe', $user->getLastname());
    }

    public function testRegisterWithExistingEmailReturnsConflict(): void
    {
        // Créer un utilisateur existant
        $existingUser = new User();
        $existingUser->setEmail('existing@example.com');
        $existingUser->setFirstname('Jane');
        $existingUser->setLastname('Doe');
        $existingUser->setPassword('hashedpassword');
        $existingUser->setRoles(['ROLE_USER']);
        
        $this->manager->persist($existingUser);
        $this->manager->flush();

        // Tenter de créer un compte avec le même email
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'email' => 'existing@example.com',
            'password' => 'NewPass123!',
            'firstname' => 'John',
            'lastname' => 'Smith',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testRegisterWithMissingEmailReturnsBadRequest(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,        ], json_encode([
            'password' => 'Pass123!',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterWithMissingPasswordReturnsBadRequest(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'email' => 'test@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterWithInvalidEmailReturnsBadRequest(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'email' => 'not-an-email',
            'password' => 'Pass123!',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterWithWeakPasswordReturnsBadRequest(): void
    {
        $this->client->request('POST', '/api/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'email' => 'test@example.com',
            'password' => '123', // Password trop court
            'firstname' => 'John',
            'lastname' => 'Doe',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
