<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface as EmailTwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TwoFactorSetupController extends AbstractController
{
    #[Route('/mon-compte/2fa', name: 'app_two_factor_setup', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        TotpAuthenticatorInterface $totpAuthenticator,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->requiresTotp($user)) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $session = $request->getSession();
        $pendingSecret = $session->get('two_factor_pending_secret');
        if (null === $pendingSecret) {
            $pendingSecret = $totpAuthenticator->generateSecret();
            $session->set('two_factor_pending_secret', $pendingSecret);
        }
        $user->setTotpSecret($pendingSecret);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('two_factor_setup', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $entityManager->flush();
            $session->remove('two_factor_pending_secret');
            $this->addFlash('success', '2FA activee.');

            return new RedirectResponse($request->headers->get('referer') ?? $this->generateUrl('homepage'));
        }

        $qrContent = $totpAuthenticator->getQRContent($user);
        $writer = new SvgWriter();
        $qrCode = new QrCode(data: $qrContent, size: 220, margin: 0);
        $qrResult = $writer->write($qrCode);

        return $this->render('security/two_factor_setup.html.twig', [
            'qr_content' => $qrContent,
            'qr_data_uri' => $qrResult->getDataUri(),
            'secret' => $user->getTotpSecret(),
        ]);
    }

    #[Route('/mon-compte/2fa/qr', name: 'app_two_factor_qr', methods: ['GET'])]
    public function qrCode(TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        if (!$this->requiresTotp($user)) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        if (null === $user->getTotpSecret()) {
            throw $this->createNotFoundException('Secret 2FA manquant.');
        }

        $qrContent = $totpAuthenticator->getQRContent($user);
        $writer = new SvgWriter();
        $qrCode = new QrCode(data: $qrContent, size: 220, margin: 0);
        $result = $writer->write($qrCode);

        return new Response($result->getString(), Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    #[Route('/2fa/resend', name: 'app_two_factor_resend', methods: ['POST'])]
    public function resendEmailCode(Request $request, CodeGeneratorInterface $codeGenerator): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('two_factor_resend', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->getUser();
        if (!$user instanceof EmailTwoFactorInterface || !$user->isEmailAuthEnabled()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $codeGenerator->generateAndSend($user);
        $this->addFlash('success', 'Code renvoye.');

        return new RedirectResponse($this->generateUrl('2fa_login'));
    }

    private function requiresTotp(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_VENDOR', $roles, true);
    }
}
