<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fournit un accès simplifié à l'utilisateur "courant" pour les templates.
 * On tente d'abord de récupérer l'utilisateur connecté (si un système de connexion
 * existe), sinon on tombe sur l'ID stocké en session juste après l'inscription.
 */
class UserContextExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Permet d'utiliser {{ viewer_user() }} dans Twig.
     *
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('viewer_user', [$this, 'resolveViewer']),
        ];
    }

    /**
     * Retourne l'utilisateur à afficher dans l'en-tête (User ou null).
     */
    public function resolveViewer(): ?User
    {
        $current = $this->security->getUser();
        if ($current instanceof User) {
            return $current;
        }

        $session = $this->requestStack->getSession();
        if ($session->has('recent_user_id')) {
            $userId = $session->get('recent_user_id');
            if (is_numeric($userId)) {
                $user = $this->userRepository->find((int) $userId);
                if ($user instanceof User && !$user->isDeleted()) {
                    return $user;
                }
            }
        }

        return null;
    }
}
