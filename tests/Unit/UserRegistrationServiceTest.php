<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserRegistrationService;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Vérifie que UserRegistrationService applique bien ses règles métier.
 */
class UserRegistrationServiceTest extends TestCase
{
    public function testRegisterCreatesUserAndReturnsToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);

        $emailVerification = $this->createMock(EmailVerificationService::class);

        $service = new UserRegistrationService(
            $entityManager,
            $userRepository,
            $passwordHasher,
            $jwtManager,
            $emailVerification
        );
        $emailVerification
            ->expects(self::once())
            ->method('prepareVerification')
            ->with(self::isInstanceOf(User::class))
            ->willReturn('verification-token');

        $emailVerification
            ->expects(self::once())
            ->method('sendVerification')
            ->with(self::isInstanceOf(User::class), 'verification-token');

        $requestData = [
            'email' => ' John.Doe@example.com ',
            'password' => 'super-secret',
            'firstname' => ' John ',
            'lastname' => ' Doe ',
        ];

        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'john.doe@example.com'])
            ->willReturn(null);

        $passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(User::class), 'super-secret')
            ->willReturn('hashed-password');

        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(User::class))
            ->willReturnCallback(function (User $user) {
                $reflection = new \ReflectionProperty(User::class, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($user, 42);
            });

        $entityManager
            ->expects(self::once())
            ->method('flush');

        $jwtManager
            ->expects(self::once())
            ->method('create')
            ->with(self::isInstanceOf(User::class))
            ->willReturn('jwt-token');

        $result = $service->register($requestData);

        self::assertSame(Response::HTTP_CREATED, $result['status']);
        self::assertSame('jwt-token', $result['data']['token']);
        self::assertSame(42, $result['data']['user']['id']);
        self::assertSame('john.doe@example.com', $result['data']['user']['email']);
        self::assertSame('John', $result['data']['user']['firstname']);
        self::assertSame('Doe', $result['data']['user']['lastname']);
    }

    public function testRegisterReturnsErrorsWhenRequestDataInvalid(): void
    {
        $service = new UserRegistrationService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserRepository::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(JWTTokenManagerInterface::class),
            $this->createMock(EmailVerificationService::class)
        );

        $result = $service->register([
            'email' => 'not-an-email',
            'password' => 'short',
            'firstname' => '',
            'lastname' => '',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $result['status']);
        self::assertArrayHasKey('email', $result['errors']);
        self::assertArrayHasKey('password', $result['errors']);
        self::assertArrayHasKey('firstname', $result['errors']);
        self::assertArrayHasKey('lastname', $result['errors']);
    }
}
