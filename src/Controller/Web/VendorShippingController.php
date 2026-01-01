<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\ShippingMethod;
use App\Entity\ShippingRate;
use App\Entity\ShippingZone;
use App\Entity\Shop;
use App\Entity\User;
use App\Form\Vendor\ShippingMethodType;
use App\Form\Vendor\ShippingRateType;
use App\Form\Vendor\ShippingZoneType;
use App\Repository\ShippingMethodRepository;
use App\Repository\ShippingRateRepository;
use App\Repository\ShippingZoneRepository;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-espace-vendeur/livraison')]
final class VendorShippingController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly ShopRepository $shopRepository,
        private readonly ShippingZoneRepository $zoneRepository,
        private readonly ShippingMethodRepository $methodRepository,
        private readonly ShippingRateRepository $rateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'app_vendor_shipping_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $shop = $this->resolveShop($request);

        $zones = $this->zoneRepository->findBy(['shop' => $shop], ['name' => 'ASC']);
        $methods = $this->methodRepository->findBy(['shop' => $shop], ['sortOrder' => 'ASC', 'name' => 'ASC']);
        $rates = $this->rateRepository->createQueryBuilder('rate')
            ->leftJoin('rate.method', 'method')
            ->andWhere('method.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('method.name', 'ASC')
            ->addOrderBy('rate.minWeight', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('vendor/shipping/index.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_shipping_index'),
            'shop' => $shop,
            'zones' => $zones,
            'methods' => $methods,
            'rates' => $rates,
        ]);
    }

    #[Route('/zones/nouvelle', name: 'app_vendor_shipping_zone_new', methods: ['GET', 'POST'])]
    public function newZone(Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $zone = (new ShippingZone())->setShop($shop);

        $form = $this->createForm(ShippingZoneType::class, $zone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($zone);
            $this->entityManager->flush();

            $this->addFlash('success', 'Zone de livraison créée.');

            return $this->redirectToRoute('app_vendor_shipping_index');
        }

        return $this->render('vendor/shipping/zone_form.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_shipping_index'),
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => false,
        ]);
    }

    #[Route('/zones/{id}/modifier', name: 'app_vendor_shipping_zone_edit', methods: ['GET', 'POST'])]
    public function editZone(ShippingZone $zone, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $this->denyAccessUnlessGranted('ROLE_VENDOR');
        if ($zone->getShop()?->getId() !== $shop->getId()) {
            throw $this->createNotFoundException('Zone introuvable.');
        }

        $form = $this->createForm(ShippingZoneType::class, $zone);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Zone mise à jour.');

            return $this->redirectToRoute('app_vendor_shipping_index');
        }

        return $this->render('vendor/shipping/zone_form.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_shipping_index'),
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => true,
        ]);
    }

    #[Route('/zones/{id}/supprimer', name: 'app_vendor_shipping_zone_delete', methods: ['POST'])]
    public function deleteZone(ShippingZone $zone, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        if (!$this->isCsrfTokenValid('delete_zone_'.$zone->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }
        if ($zone->getShop()?->getId() !== $shop->getId()) {
            throw $this->createNotFoundException('Zone introuvable.');
        }

        $this->entityManager->remove($zone);
        $this->entityManager->flush();
        $this->addFlash('success', 'Zone supprimée.');

        return $this->redirectToRoute('app_vendor_shipping_index');
    }

    #[Route('/methodes/nouvelle', name: 'app_vendor_shipping_method_new', methods: ['GET', 'POST'])]
    public function newMethod(Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $method = (new ShippingMethod())->setShop($shop);

        $form = $this->createForm(ShippingMethodType::class, $method, ['shop' => $shop]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($method);
            $this->entityManager->flush();

            $this->addFlash('success', 'Méthode de livraison créée.');

            return $this->redirectToRoute('app_vendor_shipping_index');
        }

        return $this->render('vendor/shipping/method_form.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_shipping_index'),
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => false,
        ]);
    }

    #[Route('/methodes/{id}/modifier', name: 'app_vendor_shipping_method_edit', methods: ['GET', 'POST'])]
    public function editMethod(ShippingMethod $method, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        if ($method->getShop()?->getId() !== $shop->getId()) {
            throw $this->createNotFoundException('Méthode introuvable.');
        }

        $form = $this->createForm(ShippingMethodType::class, $method, ['shop' => $shop]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Méthode mise à jour.');

            return $this->redirectToRoute('app_vendor_shipping_index');
        }

        return $this->render('vendor/shipping/method_form.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_shipping_index'),
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => true,
        ]);
    }

    #[Route('/methodes/{id}/supprimer', name: 'app_vendor_shipping_method_delete', methods: ['POST'])]
    public function deleteMethod(ShippingMethod $method, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        if (!$this->isCsrfTokenValid('delete_method_'.$method->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }
        if ($method->getShop()?->getId() !== $shop->getId()) {
            throw $this->createNotFoundException('Méthode introuvable.');
        }

        $this->entityManager->remove($method);
        $this->entityManager->flush();
        $this->addFlash('success', 'Méthode supprimée.');

        return $this->redirectToRoute('app_vendor_shipping_index');
    }

    #[Route('/tarifs/nouveau', name: 'app_vendor_shipping_rate_new', methods: ['GET', 'POST'])]
    public function newRate(Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $rate = new ShippingRate();

        $form = $this->createForm(ShippingRateType::class, $rate, ['shop' => $shop]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($rate);
            $this->entityManager->flush();
            $this->addFlash('success', 'Tarif ajouté.');

            return $this->redirectToRoute('app_vendor_shipping_index');
        }

        return $this->render('vendor/shipping/rate_form.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_shipping_index'),
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => false,
        ]);
    }

    #[Route('/tarifs/{id}/modifier', name: 'app_vendor_shipping_rate_edit', methods: ['GET', 'POST'])]
    public function editRate(ShippingRate $rate, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $method = $rate->getMethod();
        if (!$method || $method->getShop()?->getId() !== $shop->getId()) {
            throw $this->createNotFoundException('Tarif introuvable.');
        }

        $form = $this->createForm(ShippingRateType::class, $rate, ['shop' => $shop]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Tarif mis à jour.');

            return $this->redirectToRoute('app_vendor_shipping_index');
        }

        return $this->render('vendor/shipping/rate_form.html.twig', [
            'vendor_nav' => $this->buildVendorNav('app_vendor_shipping_index'),
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => true,
        ]);
    }

    #[Route('/tarifs/{id}/supprimer', name: 'app_vendor_shipping_rate_delete', methods: ['POST'])]
    public function deleteRate(ShippingRate $rate, Request $request): Response
    {
        $shop = $this->resolveShop($request);
        $method = $rate->getMethod();
        if (!$this->isCsrfTokenValid('delete_rate_'.$rate->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }
        if (!$method || $method->getShop()?->getId() !== $shop->getId()) {
            throw $this->createNotFoundException('Tarif introuvable.');
        }

        $this->entityManager->remove($rate);
        $this->entityManager->flush();
        $this->addFlash('success', 'Tarif supprimé.');

        return $this->redirectToRoute('app_vendor_shipping_index');
    }

    private function resolveShop(Request $request): Shop
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            throw $this->createAccessDeniedException($response->getContent() ?? 'Accès refusé.');
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createNotFoundException('Aucun vendeur trouvé.');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop) {
            throw $this->createNotFoundException('Aucune boutique enregistrée.');
        }

        return $shop;
    }

    private function resolveViewer(Request $request): User
    {
        $current = $this->security->getUser();
        if ($current instanceof User) {
            return $current;
        }

        $recentId = $request->getSession()->get('recent_user_id');
        if ($recentId) {
            $user = $this->userRepository->find((int) $recentId);
            if ($user instanceof User) {
                return $user;
            }
        }

        throw $this->createAccessDeniedException('Utilisateur requis.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildVendorNav(string $activeRoute): array
    {
        return [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => 'app_vendor_shop_new' === $activeRoute, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => 'app_vendor_products' === $activeRoute, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => 'app_vendor_attributes' === $activeRoute, 'path' => 'app_vendor_attributes'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => 'app_vendor_orders' === $activeRoute, 'path' => 'app_vendor_orders'],
            ['label' => 'Retours', 'icon' => '↩️', 'active' => 'app_vendor_returns' === $activeRoute, 'path' => 'app_vendor_returns'],
            ['label' => 'Livraison', 'icon' => '🚚', 'active' => 'app_vendor_shipping_index' === $activeRoute, 'path' => 'app_vendor_shipping_index'],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => 'app_vendor_stats' === $activeRoute, 'path' => 'app_vendor_stats'],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];
    }
}
