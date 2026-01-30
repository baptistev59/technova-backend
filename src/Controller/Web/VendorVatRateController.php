<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Shop;
use App\Entity\VatRate;
use App\Form\Vendor\VatRateType;
use App\Repository\VatRateRepository;
use App\Repository\ShopRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-espace-vendeur/taux-tva')]
class VendorVatRateController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly VatRateRepository $vatRateRepository,
        private readonly ShopRepository $shopRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_vendor_vatrates', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        $rates = $this->vatRateRepository->findBy(['shop' => $shop]);

        return $this->render('vendor/vatrate/index.html.twig', [
            'rates' => $rates,
            'vendor_nav' => $this->navigation('app_vendor_vatrates'),
        ]);
    }

    #[Route('/nouveau', name: 'app_vendor_vatrates_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        $rate = new VatRate();
        $rate->setShop($shop);

        $form = $this->createForm(VatRateType::class, $rate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($rate);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le taux "%s" a été créé.', $rate->getLabel() ?? $rate->getCode()));

            return $this->redirectToRoute('app_vendor_vatrates');
        }

        return $this->render('vendor/vatrate/form.html.twig', [
            'form' => $form,
            'rate' => $rate,
            'is_edit' => false,
            'vendor_nav' => $this->navigation('app_vendor_vatrates'),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_vendor_vatrates_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, VatRate $rate): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        if ($rate->getShop() && $rate->getShop() !== $shop) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(VatRateType::class, $rate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Le taux "%s" a été mis à jour.', $rate->getLabel() ?? $rate->getCode()));

            return $this->redirectToRoute('app_vendor_vatrates');
        }

        return $this->render('vendor/vatrate/form.html.twig', [
            'form' => $form,
            'rate' => $rate,
            'is_edit' => true,
            'vendor_nav' => $this->navigation('app_vendor_vatrates'),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_vendor_vatrates_delete', methods: ['POST'])]
    public function delete(Request $request, VatRate $rate): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        if ($rate->getShop() !== $shop) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('vatrate_delete_'.$rate->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($rate);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le taux a bien été supprimé.');
        } else {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');
        }

        return $this->redirectToRoute('app_vendor_vatrates');
    }

    private function guardViewer(Request $request): ?Response
    {
        $user = $this->security->getUser();
        if ($response = $this->viewerAccessChecker->requireViewer($user, $request->getSession())) {
            return $response;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function navigation(string $activeRoute): array
    {
        return [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => 'app_vendor_shop_new' === $activeRoute, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => 'app_vendor_products' === $activeRoute, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => 'app_vendor_attributes' === $activeRoute, 'path' => 'app_vendor_attributes'],
            ['label' => 'Taux TVA', 'icon' => '💱', 'active' => 'app_vendor_vatrates' === $activeRoute, 'path' => 'app_vendor_vatrates'],
            ['label' => 'Zones TVA', 'icon' => '🌍', 'active' => 'app_vendor_taxzones' === $activeRoute, 'path' => 'app_vendor_taxzones'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => 'app_vendor_orders' === $activeRoute, 'path' => 'app_vendor_orders'],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];
    }

    private function resolveShop(Request $request): Shop
    {
        $user = $this->security->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createAccessDeniedException('Crée ta boutique avant de gérer tes taux de TVA.');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        return $shop;
    }
}
