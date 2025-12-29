<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class EmailVerificationService
{
    private const TOKEN_TTL = '+24 hours';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly ParameterBagInterface $params,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'monolog.logger.email')]
        private readonly LoggerInterface $emailLogger,
        private readonly ?string $mailerFrom = null,
    ) {
    }

    public function prepareVerification(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user
            ->setEmailVerificationToken($token)
            ->setEmailVerificationExpiresAt(new \DateTimeImmutable(self::TOKEN_TTL))
            ->setIsEmailVerified(false);

        return $token;
    }

    public function sendVerification(User $user, string $token): void
    {
        $email = $user->getEmail();
        if (!$email) {
            return;
        }

        $fromAddress = $this->mailerFrom
            ? Address::create($this->mailerFrom)
            : new Address('no-reply@technova.local', 'TechNova');

        $verificationUrl = $this->generateVerificationUrl($token);

        $message = (new TemplatedEmail())
            ->from($fromAddress)
            ->to($email)
            ->subject('TechNova — Confirme ton adresse email')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->textTemplate('emails/verify_email.text.twig')
            ->context([
                'user' => $user,
                'verification_url' => $verificationUrl,
            ]);

        $this->mailer->send($message);
        $this->emailLogger->info('Email verification sent', [
            'email' => $email,
        ]);
    }

    private function generateVerificationUrl(string $token): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $this->urlGenerator->generate('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $defaultUri = $this->params->get('router.default_uri');
        if (is_string($defaultUri) && '' !== $defaultUri) {
            $path = $this->urlGenerator->generate('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_PATH);
            return rtrim($defaultUri, '/').$path;
        }

        return $this->urlGenerator->generate('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
