<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Address;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileType;
use App\Repository\UserRepository;
use App\Security\ViewerAccessChecker;
use App\Service\UserAnonymizer;
use App\Service\UserProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly UserRepository $userRepository,
        private readonly UserProfileService $profileService,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly UserAnonymizer $userAnonymizer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/mon-compte/profil', name: 'app_profile', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        if (!$user instanceof User) {
            $this->addFlash('info', 'Crée un compte ou connecte-toi pour accéder au profil.');

            return $this->redirectToRoute('app_register');
        }

        $primaryAddress = $this->profileService->guessPrimaryAddress($user)
            ?? (new Address())->setIsDefault(true)->setIsShipping(true)->setIsBilling(true);

        $form = $this->createForm(ProfileType::class, $user, [
            'primary_address' => $primaryAddress,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleAvatarUpload($form->get('avatarFile')->getData(), $user);
            /** @var Address|null $submittedAddress */
            $submittedAddress = $form->get('primaryAddress')->getData();
            $this->profileService->applyProfileUpdates($user, $submittedAddress);
            $this->addFlash('success', 'Profil mis à jour avec succès.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('account/profile.html.twig', [
            'profileForm' => $form->createView(),
            'viewer' => $user,
        ]);
    }

    #[Route('/mon-compte/profil/supprimer', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        if (!$this->isCsrfTokenValid('profile_delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $user = $this->resolveViewer($request);

        $this->userAnonymizer->anonymize($user);

        $session = $request->getSession();
        $session->invalidate();
        $this->security->logout(false);

        $this->addFlash('success', 'Ton compte a été supprimé et anonymisé.');

        return $this->redirectToRoute('homepage');
    }

    #[Route('/mon-compte/changer-mot-de-passe', name: 'app_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        if (!$user instanceof User) {
            $this->addFlash('info', 'Crée un compte ou connecte-toi pour accéder au profil.');

            return $this->redirectToRoute('app_register');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            $this->addFlash('success', 'Ton mot de passe a été modifié avec succès.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('account/change_password.html.twig', [
            'changePasswordForm' => $form->createView(),
        ]);
    }

    private function resolveViewer(Request $request): User
    {
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            return $currentUser;
        }

        $recentId = $request->getSession()->get('recent_user_id');
        if ($recentId) {
            $user = $this->userRepository->find($recentId);
            if ($user instanceof User) {
                return $user;
            }
        }

        throw $this->createAccessDeniedException('Utilisateur requis.');
    }

    private function handleAvatarUpload(mixed $file, User $user): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }

        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/avatars';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->addFlash('error', 'Impossible de créer le dossier des avatars.');

            return;
        }

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('avatar-%s.%s', bin2hex(random_bytes(6)), $extension);

        try {
            $this->deleteAvatarFile($user->getAvatarPath());
            $file->move($uploadDir, $filename);
            $user->setAvatarPath('uploads/avatars/'.$filename);
        } catch (FileException) {
            $this->addFlash('error', 'Le téléchargement de ton avatar a échoué.');
        }
    }

    private function deleteAvatarFile(?string $relativePath): void
    {
        if (!$relativePath || !str_starts_with($relativePath, 'uploads/avatars/')) {
            return;
        }

        $absolute = $this->getParameter('kernel.project_dir').'/public/'.ltrim($relativePath, '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
