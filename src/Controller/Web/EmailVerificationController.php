<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/verification/email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verify(string $token): Response
    {
        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);
        if (!$user) {
            $this->addFlash('error', 'Lien de confirmation invalide.');

            return $this->render('security/verify_email.html.twig', [
                'status' => 'invalid',
            ]);
        }

        if ($user->isEmailVerified()) {
            return $this->render('security/verify_email.html.twig', [
                'status' => 'already_verified',
            ]);
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();
        if ($expiresAt && $expiresAt < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Lien de confirmation expiré.');

            return $this->render('security/verify_email.html.twig', [
                'status' => 'expired',
            ]);
        }

        $user->clearEmailVerification();
        $this->entityManager->flush();

        $this->addFlash('success', 'Adresse email confirmée. Tu peux te connecter.');

        return $this->render('security/verify_email.html.twig', [
            'status' => 'verified',
        ]);
    }
}
