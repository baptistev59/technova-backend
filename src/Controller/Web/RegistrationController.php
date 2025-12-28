<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Form\RegistrationType;
use App\Service\UserRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Formulaire web d'inscription classique.
 */
class RegistrationController extends AbstractController
{
    public function __construct(private readonly UserRegistrationService $registrationService)
    {
    }

    /**
     * Affiche + traite le formulaire traditionnel.
     */
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        $errors = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $requestData = $form->getData();
            $result = $this->registrationService->register($requestData);

            if (Response::HTTP_CREATED === $result['status']) {
                $this->addFlash('success', 'Bienvenue sur TechNova ! Le profil est prêt à être complété.');
                if (isset($result['data']['user']['id'])) {
                    $request->getSession()->set('recent_user_id', $result['data']['user']['id']);
                }

                return $this->redirectToRoute('app_profile');
            }

            $errors = implode(' ', $result['errors'] ?? ['Une erreur est survenue.']);
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
            'apiError' => $errors,
        ]);
    }
}
