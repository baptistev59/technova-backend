<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Shop;
use App\Entity\TaxZone;
use App\Entity\User;
use App\Form\Vendor\TaxZoneType;
use App\Repository\ShopRepository;
use App\Repository\TaxZoneRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-espace-vendeur/zones-tva')]
class VendorTaxZoneController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly TaxZoneRepository $taxZoneRepository,
        private readonly ShopRepository $shopRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_vendor_taxzones', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        $presets = $this->taxZoneRepository->findPresets();
        $custom = $this->taxZoneRepository->findCustomByShop($shop);

        return $this->render('vendor/taxzone/index.html.twig', [
            'presets' => $presets,
            'custom' => $custom,
            'vendor_nav' => $this->navigation('app_vendor_taxzones'),
        ]);
    }

    #[Route('/nouveau', name: 'app_vendor_taxzones_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        $zone = new TaxZone();
        $zone->setShop($shop);
        $zone->setActive(true);

        $form = $this->createForm(TaxZoneType::class, $zone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer un code unique pour la zone personnalisée
            $baseCode = strtoupper(preg_replace('/[^a-z0-9]/i', '_', $zone->getName()));
            $code = $baseCode ?: 'CUSTOM';
            $suffix = 1;
            while ($this->taxZoneRepository->findOneBy(['code' => $code, 'shop' => $shop])) {
                $code = sprintf('%s_%d', $baseCode ?: 'CUSTOM', $suffix);
                ++$suffix;
            }
            $zone->setCode($code);

            $this->entityManager->persist($zone);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('La zone « %s » a été créée.', $zone->getName()));

            return $this->redirectToRoute('app_vendor_taxzones');
        }

        return $this->render('vendor/taxzone/form.html.twig', [
            'form' => $form,
            'zone' => $zone,
            'is_edit' => false,
            'vendor_nav' => $this->navigation('app_vendor_taxzones'),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_vendor_taxzones_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TaxZone $zone): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);

        // Vérifier que la zone appartient à la boutique et qu'elle n'est pas prédéfinie
        if ($zone->getShop() !== $shop) {
            throw $this->createNotFoundException();
        }

        if ($zone->isPreset()) {
            $this->addFlash('error', 'Les zones prédéfinies ne peuvent pas être modifiées. Crée une copie personnalisée.');

            return $this->redirectToRoute('app_vendor_taxzones');
        }

        $form = $this->createForm(TaxZoneType::class, $zone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('La zone « %s » a été mise à jour.', $zone->getName()));

            return $this->redirectToRoute('app_vendor_taxzones');
        }

        return $this->render('vendor/taxzone/form.html.twig', [
            'form' => $form,
            'zone' => $zone,
            'is_edit' => true,
            'vendor_nav' => $this->navigation('app_vendor_taxzones'),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_vendor_taxzones_delete', methods: ['POST'])]
    public function delete(Request $request, TaxZone $zone): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);

        if ($zone->getShop() !== $shop) {
            throw $this->createNotFoundException();
        }

        if ($zone->isPreset()) {
            $this->addFlash('error', 'Les zones prédéfinies ne peuvent pas être supprimées.');

            return $this->redirectToRoute('app_vendor_taxzones');
        }

        if ($this->isCsrfTokenValid('taxzone_delete_'.$zone->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($zone);
            $this->entityManager->flush();
            $this->addFlash('success', 'La zone a bien été supprimée.');
        } else {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');
        }

        return $this->redirectToRoute('app_vendor_taxzones');
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
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createAccessDeniedException('Crée ta boutique avant de gérer tes zones de TVA.');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        return $shop;
    }
}
