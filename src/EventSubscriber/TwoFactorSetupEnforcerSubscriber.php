<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TwoFactorSetupEnforcerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!$this->requiresTotp($user) || null !== $user->getTotpSecret()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');

        if (in_array($route, ['app_two_factor_setup', 'app_logout', 'app_login'], true)) {
            return;
        }

        if (str_starts_with((string) $request->getPathInfo(), '/api')) {
            return;
        }

        $acceptHeader = (string) $request->headers->get('Accept');
        if ('' !== $acceptHeader && !str_contains($acceptHeader, 'text/html')) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_two_factor_setup')));
    }

    private function requiresTotp(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_VENDOR', $roles, true);
    }
}
