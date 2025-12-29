<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service dédié à l'inscription : transforme les données brutes du formulaire en entité User persistée.
 */
class UserRegistrationService
{
    private const DEFAULT_AVATAR = '/images/avatars/avatar-customer.svg';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EmailVerificationService $emailVerificationService,
    ) {
    }

    /**
     * Enregistre un utilisateur à partir des données de requête validées.
     *
     * @param array<string, string> $requestData
     *
     * @return array{
     *     status: int,
     *     data?: array{token: string, user: array{id: int, email: string, firstname: string, lastname: string}},
     *     errors?: array<string, string>
     * }
     */
    public function register(array $requestData): array
    {
        $errors = $this->validateRequestData($requestData);

        if (!empty($errors)) {
            return [
                'status' => Response::HTTP_BAD_REQUEST,
                'errors' => $errors,
            ];
        }

        $email = strtolower(trim($requestData['email']));

        if ($this->userRepository->findOneBy(['email' => $email])) {
            return [
                'status' => Response::HTTP_CONFLICT,
                'errors' => ['email' => 'Un compte utilise déjà cet email'],
            ];
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstname(trim($requestData['firstname']))
            ->setLastname(trim($requestData['lastname']))
            ->setRoles(['ROLE_USER'])
            ->setAvatarPath(self::DEFAULT_AVATAR);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $requestData['password']);
        $user->setPassword($hashedPassword);
        $verificationToken = $this->emailVerificationService->prepareVerification($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->emailVerificationService->sendVerification($user, $verificationToken);
        $token = $this->jwtManager->create($user);

        return [
            'status' => Response::HTTP_CREATED,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                ],
            ],
        ];
    }

    /**
     * Vérifie les règles métiers minimales pour les données d'inscription.
     *
     * @param array<string, string> $requestData
     *
     * @return array<string, string>
     */
    private function validateRequestData(array $requestData): array
    {
        $errors = [];

        $email = strtolower(trim($requestData['email'] ?? ''));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide';
        }

        $password = $requestData['password'] ?? '';
        if (strlen($password) < 8) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }

        if ('' === trim($requestData['firstname'] ?? '')) {
            $errors['firstname'] = 'Le prénom est obligatoire.';
        }

        if ('' === trim($requestData['lastname'] ?? '')) {
            $errors['lastname'] = 'Le nom est obligatoire.';
        }

        return $errors;
    }
}
