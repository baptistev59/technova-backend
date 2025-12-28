<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérifie la présence d'un utilisateur « viewer » pour les écrans Twig.
 *
 * Les pages panier/profil utilisent encore une session personnalisée
 * (clé recent_user_id). Cette classe centralise la vérification afin
 * d'éviter de dupliquer la logique dans chaque contrôleur.
 */
class ViewerAccessChecker
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Retourne null si l'utilisateur est encore reconnu, sinon redirige vers /connexion.
     */
    public function requireViewer(?UserInterface $user, ?SessionInterface $session): ?Response
    {
        if ($user instanceof UserInterface) {
            return null;
        }

        if ($session && $session->has('recent_user_id')) {
            return null;
        }

        if ($session instanceof Session) {
            $session->getFlashBag()->add('warning', 'Votre session a expiré. Merci de vous reconnecter pour continuer vos achats.');
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
