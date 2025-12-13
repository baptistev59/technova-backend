<?php

namespace App\Controller\Web;

use App\Entity\AttributeDefinition;
use App\Entity\Shop;
use App\Entity\User;
use App\Form\Vendor\AttributeDefinitionType;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\ShopRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/mon-espace-vendeur/attributs')]
class VendorAttributeController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly AttributeDefinitionRepository $attributeRepository,
        private readonly ShopRepository $shopRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {
    }

    #[Route('', name: 'app_vendor_attributes', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        $attributes = $this->attributeRepository->findBy(['shop' => $shop], ['position' => 'ASC', 'name' => 'ASC', 'slug' => 'ASC']);

        return $this->render('vendor/attribute/index.html.twig', [
            'attributes' => $attributes,
            'vendor_nav' => $this->navigation('app_vendor_attributes'),
        ]);
    }

    #[Route('/nouveau', name: 'app_vendor_attributes_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        $attribute = new AttributeDefinition();
        $attribute->setShop($shop);
        $form = $this->createForm(AttributeDefinitionType::class, $attribute);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleSlug($attribute, $shop);
            $this->entityManager->persist($attribute);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('L’attribut "%s" a été créé.', $attribute->getName()));

            return $this->redirectToRoute('app_vendor_attributes');
        }

        return $this->render('vendor/attribute/form.html.twig', [
            'form' => $form,
            'attribute' => $attribute,
            'is_edit' => false,
            'vendor_nav' => $this->navigation('app_vendor_attributes'),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_vendor_attributes_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AttributeDefinition $attribute): Response
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        if ($attribute->getShop() && $attribute->getShop() !== $shop) {
            throw $this->createNotFoundException();
        }
        $attribute->setShop($shop);

        $form = $this->createForm(AttributeDefinitionType::class, $attribute);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleSlug($attribute, $shop);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('L’attribut "%s" a été mis à jour.', $attribute->getName()));

            return $this->redirectToRoute('app_vendor_attributes');
        }

        return $this->render('vendor/attribute/form.html.twig', [
            'form' => $form,
            'attribute' => $attribute,
            'is_edit' => true,
            'vendor_nav' => $this->navigation('app_vendor_attributes'),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_vendor_attributes_delete', methods: ['POST'])]
    public function delete(Request $request, AttributeDefinition $attribute): RedirectResponse
    {
        if ($response = $this->guardViewer($request)) {
            return $response;
        }

        $shop = $this->resolveShop($request);
        if ($attribute->getShop() !== $shop) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('attribute_delete_' . $attribute->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($attribute);
            $this->entityManager->flush();
            $this->addFlash('success', 'L’attribut a bien été supprimé.');
        } else {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');
        }

        return $this->redirectToRoute('app_vendor_attributes');
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
            ['label' => 'Commandes', 'icon' => '📦', 'active' => false],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];
    }

    private function handleSlug(AttributeDefinition $attribute, Shop $shop): void
    {
        $name = (string) $attribute->getName();
        $shopSegment = $shop->getSlug() ?: (string) $shop->getId();
        $baseSlug = (string) $this->slugger->slug(trim($name !== '' ? $name : uniqid('attribute_', true)))->lower();
        if ($shopSegment) {
            $baseSlug = trim($baseSlug . '-' . $shopSegment, '-');
        }
        if ($baseSlug === '') {
            $baseSlug = uniqid('attribute_', true);
        }

        $slug = $baseSlug;
        $suffix = 1;

        while ($existing = $this->attributeRepository->findOneBy(['slug' => $slug, 'shop' => $shop])) {
            if ($existing->getId() === $attribute->getId()) {
                break;
            }
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            ++$suffix;
        }

        $attribute->setSlug($slug);
    }

    private function resolveShop(Request $request): Shop
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createAccessDeniedException('Crée ta boutique avant de gérer tes attributs.');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        return $shop;
    }
}
