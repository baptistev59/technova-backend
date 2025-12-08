<?php

namespace App\Controller\Web;

use App\Entity\Address;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use App\Form\Vendor\ShopType;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/mon-espace-vendeur')]
class VendorShopController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly ShopRepository $shopRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {
    }

    #[Route('/boutique', name: 'app_vendor_shop_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);

        $vendor = $user->getVendor();
        $existingShop = $vendor ? $this->shopRepository->findOneBy(['owner' => $vendor]) : null;
        $editMode = $request->query->getBoolean('edit');
        if ($request->isMethod('POST') && $existingShop instanceof Shop) {
            $editMode = true;
        }

        if ($existingShop instanceof Shop && !$editMode) {
            return $this->render('vendor/shop/existing.html.twig', [

                // MENU VENDEUR (contient active)
                'vendor_nav' => [
                    ['label' => 'Accueil', 'icon' => '🏠', 'active' => true],
                    ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => false],
                    ['label' => 'Commandes', 'icon' => '📦', 'active' => false],
                    ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
                    ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
                ],

                // STATS DU DASHBOARD (pas d'active ici)
                'stats' => [
                    [
                        'label' => 'Ventes du jour',
                        'value' => '33',
                        'trend' => '+8% cette semaine',
                        'icon'  => '🛒',
                    ],
                    [
                        'label' => 'Revenus',
                        'value' => '1 240 €',
                        'trend' => '+12% cette semaine',
                        'icon'  => '💶',
                    ],
                    [
                        'label' => 'Commandes en cours',
                        'value' => '14',
                        'trend' => '+3% cette semaine',
                        'icon'  => '📦',
                    ],
                    [
                        'label' => 'Produits actifs',
                        'value' => '56',
                        'trend' => 'Stable',
                        'icon'  => '🏬',
                    ],
                ],

                'shop' => $existingShop,
            ]);
        }

        $session = $request->getSession();
        if (!$existingShop && !$vendor && (!$session || !$session->get('vendor_terms_accepted'))) {
            $this->addFlash('warning', 'Merci de valider les conditions vendeur avant de créer ta boutique.');

            return $this->redirectToRoute('app_vendor_terms');
        }

        if (!$vendor) {
            $vendor = (new Vendor())
                ->setOwner($user)
                ->setEmail($user->getEmail());
        }

        if (!$vendor->getAddress()) {
            $vendor->setAddress(new Address());
        }

        $vendorAddress = $vendor->getAddress();
        if ($vendorAddress) {
            if ($vendorAddress->isDefault() === null) {
                $vendorAddress->setIsDefault(false);
            }
            if ($vendorAddress->isBilling() === null) {
                $vendorAddress->setIsBilling(false);
            }
            if ($vendorAddress->isShipping() === null) {
                $vendorAddress->setIsShipping(false);
            }
        }

        if ($existingShop instanceof Shop && $editMode) {
            $shop = $existingShop;
        } else {
            $shop = new Shop();
            $shop->setContactEmail($vendor?->getEmail() ?? $user->getEmail() ?? '');
        }

        if (!$shop->getOwner()) {
            $shop->setOwner($vendor);
        }

        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNewShop = null === $shop->getId();
            $vendor = $shop->getOwner();
            $isNewVendor = $vendor && null === $vendor->getId();

            if ($isNewVendor) {
                if (!$vendor->getCompanyName()) {
                    $vendor->setCompanyName($shop->getName() ?: sprintf('Boutique de %s', $user->getFirstname()));
                }
                if (!$vendor->getEmail()) {
                    $vendor->setEmail($user->getEmail());
                }
                $this->entityManager->persist($vendor);

                $roles = $user->getRoles();
                if (!in_array('ROLE_VENDOR', $roles, true)) {
                    $roles[] = 'ROLE_VENDOR';
                    $user->setRoles(array_values(array_unique($roles)));
                }
            }

            $shop->setOwner($vendor);
            if ($isNewShop) {
                $shop->setSlug($this->generateUniqueSlug((string) $shop->getName()));
            }

            $logo = $form->get('logoFile')->getData();
            $banner = $form->get('bannerFile')->getData();
            $this->handleUploads($shop, $logo, $banner);

            if ($isNewShop) {
                $this->entityManager->persist($shop);
            }
            $this->entityManager->flush();

            if ($session) {
                $session->remove('vendor_terms_accepted');
            }

            $this->addFlash('success', $isNewShop ? 'Ta boutique est créée ! Tu peux maintenant ajouter tes produits.' : 'Ta boutique a été mise à jour.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        return $this->render('vendor/shop/new.html.twig', [
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => $editMode,
        ]);
    }

    #[Route('/mon-espace-vendeur/conditions', name: 'app_vendor_terms', methods: ['GET', 'POST'])]
    public function terms(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();
        if ($vendor && $this->shopRepository->findOneBy(['owner' => $vendor])) {
            $this->addFlash('info', 'Tu as déjà une boutique active.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $form = $this->createFormBuilder()
            ->add('accept', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'J’accepte les conditions générales vendeur TechNova.',
                'mapped' => false,
                'required' => true,
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()?->set('vendor_terms_accepted', true);

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        return $this->render('vendor/shop/terms.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function resolveViewer(Request $request): User
    {
        $current = $this->security->getUser();
        if ($current instanceof User) {
            return $current;
        }

        $recentId = $request->getSession()?->get('recent_user_id');
        if ($recentId) {
            $user = $this->userRepository->find((int) $recentId);
            if ($user instanceof User) {
                return $user;
            }
        }

        throw $this->createAccessDeniedException('Utilisateur requis.');
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = strtolower($this->slugger->slug($name)->toString());
        if ($baseSlug === '') {
            $baseSlug = 'boutique';
        }

        $slug = $baseSlug;
        $suffix = 1;

        while ($this->shopRepository->findOneBy(['slug' => $slug])) {
            $slug = sprintf('%s-%d', $baseSlug, ++$suffix);
        }

        return $slug;
    }

    private function handleUploads(Shop $shop, mixed $logoFile, mixed $bannerFile): void
    {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/shops';
        $filesystem = new Filesystem();
        if (!$filesystem->exists($uploadDir)) {
            $filesystem->mkdir($uploadDir, 0775);
        }

        if ($logoFile instanceof UploadedFile) {
            $filename = sprintf('shop-logo-%s.%s', uniqid(), $logoFile->guessExtension() ?: 'bin');
            $logoFile->move($uploadDir, $filename);
            $shop->setLogo('uploads/shops/' . $filename);
        }

        if ($bannerFile instanceof UploadedFile) {
            $filename = sprintf('shop-banner-%s.%s', uniqid(), $bannerFile->guessExtension() ?: 'bin');
            $bannerFile->move($uploadDir, $filename);
            $shop->setBanner('uploads/shops/' . $filename);
        }
    }
}
