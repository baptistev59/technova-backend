<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\WishlistRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-compte/favoris', name: 'app_wishlist')]
class WishlistController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly WishlistRepository $wishlistRepository,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: '_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('info', 'Connecte-toi pour accéder à tes favoris.');

            return $this->redirectToRoute('app_login');
        }

        // Récupère tous les favoris de l'utilisateur
        $wishlists = $this->wishlistRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('account/favorites.html.twig', [
            'wishlists' => $wishlists,
            'wishlist_count' => count($wishlists),
        ]);
    }

    #[Route('/{id}', name: '_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Connecte-toi pour gérer tes favoris.');

            return $this->redirectToRoute('app_login');
        }

        $wishlist = $this->wishlistRepository->find($id);
        if (!$wishlist || $wishlist->getUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Ce favori n\'existe pas ou ne t\'appartient pas.');

            return $this->redirectToRoute('app_wishlist_list');
        }

        // Vérifie le token CSRF
        if (!$this->isCsrfTokenValid('delete_wishlist_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Erreur de sécurité. Réessaye.');

            return $this->redirectToRoute('app_wishlist_list');
        }

        $this->entityManager->remove($wishlist);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le produit a été retiré de tes favoris.');

        return $this->redirectToRoute('app_wishlist_list');
    }
}
