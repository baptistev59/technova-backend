<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductAttributeValue;
use App\Entity\ProductBundleItem;
use App\Entity\ProductImage;
use App\Entity\ProductReview;
use App\Entity\ProductVariant;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AppFixtures extends Fixture
{
    private const IMAGE_BASE_PATH = '/images/products/';
    private const CATEGORY_ICON_BASE_PATH = '/images/categories/';
    private const AVATAR_BASE_PATH = '/images/avatars/';
    private const ADMIN_AVATAR = self::AVATAR_BASE_PATH.'avatar-admin.svg';
    private const VENDOR_AVATAR = self::AVATAR_BASE_PATH.'avatar-vendor.svg';
    private const CUSTOMER_AVATAR = self::AVATAR_BASE_PATH.'avatar-customer.svg';
    /**
     * @var array<int, User>
     */
    private array $customers = [];

    public function __construct(
        private SluggerInterface $slugger,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->createAdmin($manager);
        $this->createDemoCustomers($manager);

        $categories = $this->createCategories($manager);
        $brands = $this->createBrands($manager);
        $shops = $this->createVendorsAndShops($manager);

        $this->createProducts($manager, $categories, $brands, $shops);

        $manager->flush();
    }

    private function createAdmin(ObjectManager $manager): User
    {
        $admin = (new User())
            ->setEmail('admin@test.fr')
            ->setRoles(['ROLE_ADMIN'])
            ->setFirstname('Admin')
            ->setLastname('TechNova')
            ->setAvatarPath(self::ADMIN_AVATAR);

        $admin->setPassword($this->passwordHasher->hashPassword($admin, '123456'));

        $manager->persist($admin);

        return $admin;
    }

    private function createDemoCustomers(ObjectManager $manager): void
    {
        $customers = [
            [
                'email' => 'lena.client@technova.test',
                'firstname' => 'Lena',
                'lastname' => 'Hamon',
                'password' => 'Client#01',
                'address' => ['line1' => '12 avenue des Fleurs', 'line2' => null, 'postal' => '75002', 'city' => 'Paris', 'country' => 'FR'],
            ],
            [
                'email' => 'maxime.client@technova.test',
                'firstname' => 'Maxime',
                'lastname' => 'Dubois',
                'password' => 'Client#02',
                'address' => ['line1' => '8 rue des Peupliers', 'line2' => 'Bâtiment B', 'postal' => '69003', 'city' => 'Lyon', 'country' => 'FR'],
            ],
            [
                'email' => 'nora.client@technova.test',
                'firstname' => 'Nora',
                'lastname' => 'Keller',
                'password' => 'Client#03',
                'address' => ['line1' => '5 boulevard du Parc', 'line2' => null, 'postal' => '13008', 'city' => 'Marseille', 'country' => 'FR'],
            ],
        ];

        foreach ($customers as $data) {
            $customer = (new User())
                ->setEmail($data['email'])
                ->setFirstname($data['firstname'])
                ->setLastname($data['lastname'])
                ->setRoles(['ROLE_USER'])
                ->setAvatarPath(self::CUSTOMER_AVATAR);

            $customer->setPassword($this->passwordHasher->hashPassword($customer, $data['password']));

            $address = (new Address())
                ->setLabel('Adresse principale')
                ->setAddressLine1($data['address']['line1'])
                ->setAddressLine2($data['address']['line2'])
                ->setPostalCode($data['address']['postal'])
                ->setCity($data['address']['city'])
                ->setCountry($data['address']['country'])
                ->setIsDefault(true)
                ->setIsShipping(true)
                ->setIsBilling(true)
                ->setOwner($customer);

            $customer->addAddress($address);

            $manager->persist($customer);
            $manager->persist($address);
            $this->customers[] = $customer;
        }
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(ObjectManager $manager): array
    {
        $map = [];

        foreach ($this->getCategoryData() as $data) {
            $category = (new Category())
                ->setName($data['name'])
                ->setSlug($data['slug'])
                ->setDescription($data['description'])
                ->setIconPath($data['icon']);

            $manager->persist($category);
            $map[$data['slug']] = $category;
        }

        return $map;
    }

    /**
     * @return array<string, Brand>
     */
    private function createBrands(ObjectManager $manager): array
    {
        $map = [];

        foreach ($this->getBrandData() as $data) {
            $brand = (new Brand())
                ->setName($data['name'])
                ->setSlug($data['slug'])
                ->setDescription($data['description'])
                ->setLogoPath($data['logo']);

            $manager->persist($brand);
            $map[$data['slug']] = $brand;
        }

        return $map;
    }

    /**
     * @return array<string, Shop>
     */
    private function createVendorsAndShops(ObjectManager $manager): array
    {
        $shops = [];
        $shopDefinitions = $this->getShopData();

        foreach ($this->getVendorData() as $data) {
            $owner = (new User())
                ->setEmail($data['owner']['email'])
                ->setFirstname($data['owner']['firstname'])
                ->setLastname($data['owner']['lastname'])
                ->setRoles(['ROLE_VENDOR'])
                ->setAvatarPath(self::VENDOR_AVATAR);

            $owner->setPassword($this->passwordHasher->hashPassword($owner, $data['owner']['password']));

            $manager->persist($owner);

            $vendor = (new Vendor())
                ->setCompanyName($data['company'])
                ->setBusinessId($data['businessId'])
                ->setBusinessIdType('SIRET')
                ->setPhone($data['phone'])
                ->setEmail($data['contact'])
                ->setWebsite($data['website'])
                ->setOwner($owner);

            $manager->persist($vendor);

            $shopInfo = $shopDefinitions[$data['key']];

            $shop = (new Shop())
                ->setName($shopInfo['name'])
                ->setSlug($this->slug($shopInfo['name']))
                ->setDescription($shopInfo['description'])
                ->setLogo($shopInfo['logo'])
                ->setBanner($shopInfo['banner'])
                ->setContactEmail($shopInfo['contact'])
                ->setPolicies('Livraison express mondiale, retours gratuits sous 30 jours.')
                ->setOwner($vendor);

            $manager->persist($shop);
            $shops[$data['key']] = $shop;
        }

        return $shops;
    }

    private function createProducts(
        ObjectManager $manager,
        array $categories,
        array $brands,
        array $shops,
    ): void {
        $templates = $this->getProductTemplates();
        $reviews = $this->getReviewPool();
        $templateCount = count($templates);
        $templateIndex = 0;
        $createdByShop = [];

        foreach ($shops as $vendorKey => $shop) {
            for ($i = 0; $i < 5; ++$i) {
                $createdByShop[$vendorKey] ??= [];
                $template = $templates[$templateIndex % $templateCount];
                ++$templateIndex;

                $category = $categories[$template['category']];
                $brand = $brands[$template['brand']];

                $baseName = sprintf('%s %s edition', $template['name'], $shop->getOwner()->getCompanyName());
                $productName = trim($baseName);

                $price = $template['price'] * (1 + (\random_int(-5, 12) / 100));
                $price = round($price, 2);

                $isPublished = random_int(1, 100) > 12;
                $isOutOfStock = random_int(1, 100) < 8;
                $type = $template['type'];

                if (!in_array($type, ['simple', 'variable', 'grouped'], true)) {
                    $type = 'simple';
                }
                if (4 === $i && count($createdByShop[$vendorKey]) >= 2) {
                    $type = 'grouped';
                }

                $product = (new Product())
                    ->setName($productName)
                    ->setSlug($this->slug($productName.'-'.$vendorKey.'-'.$i))
                    ->setShortDescription($template['short'])
                    ->setDescription($template['description'])
                    ->setPrice($price)
                    ->setWeight(random_int(200, 5000) / 1000)
                    ->setSku($this->generateSku($brand->getSlug()))
                    ->setBarcode($this->generateBarcode())
                    ->setType($type)
                    ->setIsFeatured(0 === $i)
                    ->setIsPublished($isPublished)
                    ->setStock($isOutOfStock ? 0 : random_int(10, 120))
                    ->setCategory($category)
                    ->setBrand($brand)
                    ->setShop($shop);

                $this->attachImage($product, $template['image']);
                $this->attachReviews($manager, $product, $reviews);

                if ('variable' === $type) {
                    $attributeDefinitions = $template['attributes'] ?? $this->getAttributeBlueprint();
                    $attributeSets = $this->createAttributesForProduct($manager, $product, $attributeDefinitions);
                    $totalStock = $this->generateVariantsForProduct($manager, $product, $attributeSets, $price);
                    $product->setStock($totalStock);
                } elseif ('grouped' === $type) {
                    $components = array_values(array_filter(
                        $createdByShop[$vendorKey],
                        static fn (Product $item) => 'grouped' !== $item->getType()
                    ));
                    if (count($components) >= 2) {
                        $pickCount = min(3, count($components));
                        $indexes = (array) array_rand($components, $pickCount);
                        foreach ($indexes as $position => $index) {
                            $bundleItem = (new ProductBundleItem())
                                ->setComponent($components[$index])
                                ->setPosition($position)
                                ->setIsRequired($position < 2);
                            $manager->persist($bundleItem);
                            $product->addBundleItem($bundleItem);
                        }
                    } else {
                        $product->setType('simple');
                    }
                }

                $manager->persist($product);
                $createdByShop[$vendorKey][] = $product;
            }
        }
    }

    private function attachImage(Product $product, string $file): void
    {
        $absolutePath = sprintf('%s/public%s%s', dirname(__DIR__, 2), self::IMAGE_BASE_PATH, $file);

        $image = (new ProductImage())
            ->setUrl(self::IMAGE_BASE_PATH.$file)
            ->setAlt($product->getName())
            ->setTitle($product->getName())
            ->setCaption('Visual concept du produit')
            ->setPosition(0)
            ->setIsMain(true)
            ->setProduct($product)
            ->setMimeType('image/svg+xml');

        if (is_file($absolutePath)) {
            $image->setFileSize(filesize($absolutePath) ?: null);
        }

        $product->addImage($image);
    }

    private function attachReviews(ObjectManager $manager, Product $product, array $pool): void
    {
        $reviewCount = random_int(0, 6);

        for ($i = 0; $i < $reviewCount; ++$i) {
            $ratingPool = [0, 1, 2, 3, 4, 5];
            $rating = $ratingPool[array_rand($ratingPool)];
            $author = [] !== $this->customers ? $this->customers[array_rand($this->customers)] : null;

            $review = (new ProductReview())
                ->setRating((float) $rating)
                ->setComment($pool[array_rand($pool)])
                ->setAuthor($author)
                ->setProduct($product);

            $manager->persist($review);
            $product->addReview($review);
        }
    }


    private function getCategoryData(): array
    {
        return [
            [
                'slug' => 'future-laptops',
                'name' => 'Ordinateurs quantiques',
                'description' => 'Stations portables mêlant IA embarquée et calcul quantique.',
                'icon' => self::CATEGORY_ICON_BASE_PATH.'category-future-laptops.svg',
            ],
            [
                'slug' => 'smart-mobility',
                'name' => 'Mobilité électrique intelligente',
                'description' => 'Trottinettes autonomes, vélos augmentés et drones cargo.',
                'icon' => self::CATEGORY_ICON_BASE_PATH.'category-smart-mobility.svg',
            ],
            [
                'slug' => 'immersive-vr',
                'name' => 'Réalité mixte & holographie',
                'description' => 'Casques XR et projecteurs holographiques pour travailler différemment.',
                'icon' => self::CATEGORY_ICON_BASE_PATH.'category-immersive-vr.svg',
            ],
            [
                'slug' => 'bio-wearables',
                'name' => 'Wearables biométriques',
                'description' => 'Anneaux, bracelets et textiles mesurant en continu la santé.',
                'icon' => self::CATEGORY_ICON_BASE_PATH.'category-bio-wearables.svg',
            ],
            [
                'slug' => 'smart-home',
                'name' => 'Maison autonome',
                'description' => 'Domotique premium, sécurité IA et gestion énergétique.',
                'icon' => self::CATEGORY_ICON_BASE_PATH.'category-smart-home.svg',
            ],
            [
                'slug' => 'creative-ai',
                'name' => 'Audio & création assistée',
                'description' => 'Enceintes contextuelles, instruments et assistants créatifs.',
                'icon' => self::CATEGORY_ICON_BASE_PATH.'category-creative-ai.svg',
            ],
            [
                'slug' => 'personal-robotics',
                'name' => 'Robots personnels',
                'description' => 'Compagnons domestiques et robots d’assistance.',
                'icon' => self::CATEGORY_ICON_BASE_PATH.'category-personal-robotics.svg',
            ],
        ];
    }

    private function getBrandData(): array
    {
        return [
            ['slug' => 'aurora-dynamics', 'name' => 'Aurora Dynamics', 'description' => 'Ultrabooks IA et stations quantiques', 'logo' => self::IMAGE_BASE_PATH.'ai-laptop.svg'],
            ['slug' => 'pulse-mobility', 'name' => 'Pulse Mobility', 'description' => 'Mobilité électrique autonome', 'logo' => self::IMAGE_BASE_PATH.'smart-scooter.svg'],
            ['slug' => 'nexa-audio', 'name' => 'Nexa Audio', 'description' => 'Son spatial et assistants vocaux contextuels', 'logo' => self::IMAGE_BASE_PATH.'smart-speaker.svg'],
            ['slug' => 'lumina-home', 'name' => 'Lumina Home', 'description' => 'Domotique holographique et gestion énergétique', 'logo' => self::IMAGE_BASE_PATH.'iot-hub.svg'],
            ['slug' => 'orbit-robotics', 'name' => 'Orbit Robotics', 'description' => 'Robots compagnons et manutention', 'logo' => self::IMAGE_BASE_PATH.'robot-companion.svg'],
            ['slug' => 'flux-vision', 'name' => 'Flux Vision', 'description' => 'Casques XR et lunettes adaptatives', 'logo' => self::IMAGE_BASE_PATH.'vr-headset.svg'],
            ['slug' => 'solara-tech', 'name' => 'Solara Tech', 'description' => 'Textiles solaires et énergie portable', 'logo' => self::IMAGE_BASE_PATH.'solar-backpack.svg'],
            ['slug' => 'quantum-wear', 'name' => 'Quantum Wear', 'description' => 'Wearables de santé prédictive', 'logo' => self::IMAGE_BASE_PATH.'wearable-ring.svg'],
        ];
    }

    private function getVendorData(): array
    {
        return [
            [
                'key' => 'aurora-labs',
                'company' => 'Aurora Labs',
                'businessId' => 'FR-AL-9080',
                'phone' => '+33 1 86 95 00 01',
                'contact' => 'contact@auroralabs.tech',
                'website' => 'https://auroralabs.tech',
                'owner' => ['email' => 'vendor01@technova.test', 'firstname' => 'Ariane', 'lastname' => 'Lopez', 'password' => 'Vendor#01'],
            ],
            [
                'key' => 'pulse-ride',
                'company' => 'Pulse Ride Collective',
                'businessId' => 'FR-PR-1177',
                'phone' => '+33 4 92 45 60 10',
                'contact' => 'hello@pulseride.io',
                'website' => 'https://pulseride.io',
                'owner' => ['email' => 'vendor02@technova.test', 'firstname' => 'Issa', 'lastname' => 'Traoré', 'password' => 'Vendor#02'],
            ],
            [
                'key' => 'nexa-studio',
                'company' => 'Nexa Studio',
                'businessId' => 'FR-NS-2234',
                'phone' => '+33 1 70 90 22 11',
                'contact' => 'support@nexastudio.ai',
                'website' => 'https://nexastudio.ai',
                'owner' => ['email' => 'vendor03@technova.test', 'firstname' => 'Mila', 'lastname' => 'Carvalho', 'password' => 'Vendor#03'],
            ],
            [
                'key' => 'lumina-habitat',
                'company' => 'Lumina Habitat',
                'businessId' => 'FR-LH-4481',
                'phone' => '+33 1 53 45 70 21',
                'contact' => 'team@luminahabitat.eu',
                'website' => 'https://luminahabitat.eu',
                'owner' => ['email' => 'vendor04@technova.test', 'firstname' => 'Tom', 'lastname' => 'Rousseau', 'password' => 'Vendor#04'],
            ],
            [
                'key' => 'orbit-care',
                'company' => 'Orbit Care Robotics',
                'businessId' => 'FR-OC-5588',
                'phone' => '+33 2 40 50 80 30',
                'contact' => 'care@orbit-robotics.com',
                'website' => 'https://orbit-robotics.com',
                'owner' => ['email' => 'vendor05@technova.test', 'firstname' => 'Zoé', 'lastname' => 'Nguyen', 'password' => 'Vendor#05'],
            ],
            [
                'key' => 'flux-visionary',
                'company' => 'Flux Visionary',
                'businessId' => 'FR-FV-6654',
                'phone' => '+33 5 56 80 90 01',
                'contact' => 'studio@fluxvisionary.com',
                'website' => 'https://fluxvisionary.com',
                'owner' => ['email' => 'vendor06@technova.test', 'firstname' => 'Sacha', 'lastname' => 'Delcourt', 'password' => 'Vendor#06'],
            ],
            [
                'key' => 'solara-motion',
                'company' => 'Solara Motion',
                'businessId' => 'FR-SM-7789',
                'phone' => '+33 1 40 22 66 90',
                'contact' => 'contact@solaramotion.eu',
                'website' => 'https://solaramotion.eu',
                'owner' => ['email' => 'vendor07@technova.test', 'firstname' => 'Noé', 'lastname' => 'Martínez', 'password' => 'Vendor#07'],
            ],
            [
                'key' => 'quantum-ring',
                'company' => 'Quantum Ring Labs',
                'businessId' => 'FR-QR-8890',
                'phone' => '+33 6 41 25 88 10',
                'contact' => 'labs@quantumring.io',
                'website' => 'https://quantumring.io',
                'owner' => ['email' => 'vendor08@technova.test', 'firstname' => 'Clara', 'lastname' => 'Benali', 'password' => 'Vendor#08'],
            ],
            [
                'key' => 'helios-drones',
                'company' => 'Helios Drones',
                'businessId' => 'FR-HD-9012',
                'phone' => '+33 1 77 95 12 12',
                'contact' => 'air@heliosdrones.fr',
                'website' => 'https://heliosdrones.fr',
                'owner' => ['email' => 'vendor09@technova.test', 'firstname' => 'Léon', 'lastname' => 'Fabre', 'password' => 'Vendor#09'],
            ],
            [
                'key' => 'axon-dynamics',
                'company' => 'Axon Dynamics',
                'businessId' => 'FR-AD-9311',
                'phone' => '+33 3 20 55 74 33',
                'contact' => 'experience@axondynamics.com',
                'website' => 'https://axondynamics.com',
                'owner' => ['email' => 'vendor10@technova.test', 'firstname' => 'Rania', 'lastname' => 'Farouk', 'password' => 'Vendor#10'],
            ],
        ];
    }

    private function getShopData(): array
    {
        return [
            'aurora-labs' => [
                'name' => 'Aurora Flagship',
                'description' => 'Ultrabooks et stations quantiques premium.',
                'contact' => 'shop@auroralabs.tech',
                'logo' => self::IMAGE_BASE_PATH.'ai-laptop.svg',
                'banner' => self::IMAGE_BASE_PATH.'vr-headset.svg',
            ],
            'pulse-ride' => [
                'name' => 'Pulse Mobility Hub',
                'description' => 'Mobilité électrique autonome pour la ville.',
                'contact' => 'hub@pulseride.io',
                'logo' => self::IMAGE_BASE_PATH.'smart-scooter.svg',
                'banner' => self::IMAGE_BASE_PATH.'autonomous-drone.svg',
            ],
            'nexa-studio' => [
                'name' => 'Nexa Audio Studio',
                'description' => 'Solutions audio génératives pour créateurs.',
                'contact' => 'studio@nexastudio.ai',
                'logo' => self::IMAGE_BASE_PATH.'smart-speaker.svg',
                'banner' => self::IMAGE_BASE_PATH.'wearable-ring.svg',
            ],
            'lumina-habitat' => [
                'name' => 'Lumina Habitat Store',
                'description' => 'Domotique holographique et gestion d’énergie.',
                'contact' => 'boutique@luminahabitat.eu',
                'logo' => self::IMAGE_BASE_PATH.'iot-hub.svg',
                'banner' => self::IMAGE_BASE_PATH.'solar-backpack.svg',
            ],
            'orbit-care' => [
                'name' => 'Orbit Care Center',
                'description' => 'Robots compagnons et assistants domestiques.',
                'contact' => 'center@orbit-robotics.com',
                'logo' => self::IMAGE_BASE_PATH.'robot-companion.svg',
                'banner' => self::IMAGE_BASE_PATH.'vr-headset.svg',
            ],
            'flux-visionary' => [
                'name' => 'Flux Vision Experience',
                'description' => 'Casques XR et lunettes à modulation adaptative.',
                'contact' => 'experience@fluxvisionary.com',
                'logo' => self::IMAGE_BASE_PATH.'vr-headset.svg',
                'banner' => self::IMAGE_BASE_PATH.'hologram-projector.svg',
            ],
            'solara-motion' => [
                'name' => 'Solara Motion Lab',
                'description' => 'Textiles solaires et accessoires d’énergie nomade.',
                'contact' => 'lab@solaramotion.eu',
                'logo' => self::IMAGE_BASE_PATH.'solar-backpack.svg',
                'banner' => self::IMAGE_BASE_PATH.'smart-speaker.svg',
            ],
            'quantum-ring' => [
                'name' => 'Quantum Ring Store',
                'description' => 'Wearables biométriques et soins prédictifs.',
                'contact' => 'store@quantumring.io',
                'logo' => self::IMAGE_BASE_PATH.'wearable-ring.svg',
                'banner' => self::IMAGE_BASE_PATH.'ai-laptop.svg',
            ],
            'helios-drones' => [
                'name' => 'Helios Flight Shop',
                'description' => 'Drones cargo et prises de vue autonomes.',
                'contact' => 'flight@heliosdrones.fr',
                'logo' => self::IMAGE_BASE_PATH.'autonomous-drone.svg',
                'banner' => self::IMAGE_BASE_PATH.'smart-scooter.svg',
            ],
            'axon-dynamics' => [
                'name' => 'Axon Experience',
                'description' => 'Interfaces neuronales et accessoires immersifs.',
                'contact' => 'hello@axondynamics.com',
                'logo' => self::IMAGE_BASE_PATH.'hologram-projector.svg',
                'banner' => self::IMAGE_BASE_PATH.'ai-laptop.svg',
            ],
        ];
    }

    private function getProductTemplates(): array
    {
        return [
            [
                'name' => 'NovaBook Quantum',
                'category' => 'future-laptops',
                'brand' => 'aurora-dynamics',
                'image' => 'ai-laptop.svg',
                'price' => 1999,
                'type' => 'variable',
                'short' => 'Ultrabook 16" avec coprocesseur neuronal QX-5.',
                'description' => 'Double écran OLED, module IA embarqué et batterie 30h pour coder, monter ou générer des médias hors-ligne.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Helix Fold X',
                'category' => 'immersive-vr',
                'brand' => 'flux-vision',
                'image' => 'vr-headset.svg',
                'price' => 1299,
                'type' => 'variable',
                'short' => 'Casque XR multi-focal avec suivi oculaire.',
                'description' => 'Matériau respirant, résolution 5K par œil et intégration native avec les espaces collaboratifs TechNova.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Pulse Glide S',
                'category' => 'smart-mobility',
                'brand' => 'pulse-mobility',
                'image' => 'smart-scooter.svg',
                'price' => 1490,
                'type' => 'simple',
                'short' => 'Trottinette autonome avec évitement d’obstacles.',
                'description' => 'Autonomie 80km, recharge solaire latente et pilotage vocal sécurisé.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Nexa Cubic',
                'category' => 'creative-ai',
                'brand' => 'nexa-audio',
                'image' => 'smart-speaker.svg',
                'price' => 499,
                'type' => 'simple',
                'short' => 'Enceinte spatiale qui adapte la musique à l’humeur.',
                'description' => 'Analyse biométrique via les micros et génération automatique de playlists personnalisées.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Helios Freight',
                'category' => 'smart-mobility',
                'brand' => 'pulse-mobility',
                'image' => 'autonomous-drone.svg',
                'price' => 2890,
                'type' => 'variable',
                'short' => 'Drone cargo silencieux pour la logistique urbaine.',
                'description' => 'Charge utile 20kg, planification IA et parachute d’urgence.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Lumina Core Home',
                'category' => 'smart-home',
                'brand' => 'lumina-home',
                'image' => 'iot-hub.svg',
                'price' => 799,
                'type' => 'simple',
                'short' => 'Hub domotique holographique multi-room.',
                'description' => 'Projection 3D des indicateurs énergétiques, automatisation des scènes et API ouverte.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Solara Trek Pack',
                'category' => 'smart-mobility',
                'brand' => 'solara-tech',
                'image' => 'solar-backpack.svg',
                'price' => 349,
                'type' => 'grouped',
                'short' => 'Sac à dos solaire générant jusqu’à 120W.',
                'description' => 'Batterie Graphène, ports USB-C 240W et charge par induction pour drones.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Quantum Ring Pulse',
                'category' => 'bio-wearables',
                'brand' => 'quantum-wear',
                'image' => 'wearable-ring.svg',
                'price' => 299,
                'type' => 'simple',
                'short' => 'Anneau biométrique avec capteurs sanguins non invasifs.',
                'description' => 'Algorithmes prédictifs pour anticiper fatigue et micro-stress.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Orbit Neo Companion',
                'category' => 'personal-robotics',
                'brand' => 'orbit-robotics',
                'image' => 'robot-companion.svg',
                'price' => 4590,
                'type' => 'variable',
                'short' => 'Robot compagnon modulable pour les familles.',
                'description' => 'Reconnaissance émotionnelle, bras articulé modulable et contrôle vocal multi-utilisateur.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'HoloBeam Studio',
                'category' => 'immersive-vr',
                'brand' => 'flux-vision',
                'image' => 'hologram-projector.svg',
                'price' => 2490,
                'type' => 'simple',
                'short' => 'Projecteur holographique autonome 4K.',
                'description' => 'Streaming direct depuis Figma / Blender, interactivité tactile et enregistrement volumétrique.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Axon Neural Pen',
                'category' => 'creative-ai',
                'brand' => 'aurora-dynamics',
                'image' => 'ai-laptop.svg',
                'price' => 259,
                'type' => 'simple',
                'short' => 'Stylet neuronal qui retranscrit la pensée en croquis.',
                'description' => 'Capteurs EMG miniaturisés et export vectoriel instantané.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
            [
                'name' => 'Helix Micro Drone',
                'category' => 'smart-mobility',
                'brand' => 'pulse-mobility',
                'image' => 'autonomous-drone.svg',
                'price' => 990,
                'type' => 'grouped',
                'short' => 'Drone caméra autonome pour créateurs nomades.',
                'description' => 'Stabilisation 8 axes, suivi IA des sujets et transmission chiffrée.',
                'attributes' => $this->getAttributeBlueprint(),
            ],
        ];
    }

    private function getReviewPool(): array
    {
        return [
            'Autonomie impressionnante, parfait pour mes tournages en extérieur.',
            'Interface fluide et service client très réactif.',
            'S’intègre parfaitement avec mon écosystème domotique.',
            'Livré en 48h, packaging premium et documentation claire.',
            'L’IA embarquée devine réellement mes besoins, bluffant.',
        ];
    }

    private function slug(string $value): string
    {
        return strtolower($this->slugger->slug($value)->toString());
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     *
     * @return array<int, array{
     *     attribute: ProductAttribute,
     *     values: array<int, array{entity: ProductAttributeValue, definition: array}>
     * }>
     */
    private function createAttributesForProduct(ObjectManager $manager, Product $product, array $definitions): array
    {
        $sets = [];
        foreach ($definitions as $index => $definition) {
            $attribute = (new ProductAttribute())
                ->setName($definition['name'])
                ->setSlug($definition['slug'])
                ->setInputType($definition['type'] ?? 'select')
                ->setPosition($index);

            $attribute->setProduct($product);
            $manager->persist($attribute);

            $values = [];
            foreach ($definition['values'] as $valueDef) {
                $value = (new ProductAttributeValue())
                    ->setValue($valueDef['label'])
                    ->setSlug($valueDef['slug'])
                    ->setColorHex($valueDef['hex'] ?? null)
                    ->setAttribute($attribute);

                $manager->persist($value);
                $values[] = [
                    'entity' => $value,
                    'definition' => $valueDef,
                ];
            }

            $sets[] = [
                'attribute' => $attribute,
                'values' => $values,
            ];
        }

        return $sets;
    }

    private function stockRangeByType(string $type): array
    {
        return match ($type) {
            'computer' => [5, 25],
            'robot' => [2, 10],
            'drone' => [3, 15],
            'mobility' => [5, 30],
            'hub' => [10, 60],
            'wearable' => [20, 150],
            default => [10, 50],
        };
    }

    /**
     * @param array<int, array{
     *     attribute: ProductAttribute,
     *     values: array<int, array{entity: ProductAttributeValue, definition: array}>
     * }> $attributeSets
     */
    private function generateVariantsForProduct(ObjectManager $manager, Product $product, array $attributeSets, float $basePrice): int
    {
        $combinations = $this->buildAttributeCombinations($attributeSets);
        $totalStock = 0;
        $index = 1;

        foreach ($combinations as $combo) {
            $price = $basePrice;
            $configuration = [];
            $metadata = [];

            foreach ($combo as $selection) {
                $definition = $selection['definition'];
                $price += $basePrice * ($definition['priceDelta'] ?? 0);
                if (isset($definition['priceFlat'])) {
                    $price += $definition['priceFlat'];
                }

                $configuration[$selection['attribute']->getSlug()] = $selection['value']->getSlug();
                $metadata[$selection['attribute']->getSlug()] = $selection['value']->getValue();
            }

            [$min, $max] = $this->stockRangeByType($product->getType());
            $isAvailable = random_int(1, 100) > 10;
            $stock = $isAvailable ? random_int(3, 25) : 0;

            $totalStock += $stock;

            $promoPrice = null;
            if (0 === $index % 4) {
                $promoPrice = round($price * 0.9, 2);
            }

            $variant = (new ProductVariant())
                ->setProduct($product)
                ->setPrice(round($price, 2))
                ->setPromoPrice($promoPrice)
                ->setIsAvailable($isAvailable)
                ->setStock($stock)
                ->setWeight(max(0.1, $product->getWeight() + (random_int(-20, 20) / 100)))
                ->setImagePath(($product->getImages()->first() ?: null)?->getUrl())
                ->setConfiguration($configuration ?: null)
                ->setMetadata($metadata ?: null)
                ->setSku(sprintf(
                    '%s-%02d',
                    strtoupper(substr($product->getSku() ?? $product->getSlug(), 0, 6)),
                    $index
                ))
                ->setBarcode($this->generateBarcode());

            $manager->persist($variant);
            $product->addVariant($variant);
            ++$index;
        }

        return $totalStock;
    }

    /**
     * @return array<int, array<int, array{
     *     attribute: ProductAttribute,
     *     value: ProductAttributeValue,
     *     definition: array
     * }>>
     */
    private function buildAttributeCombinations(array $attributeSets): array
    {
        if (empty($attributeSets)) {
            return [[]];
        }

        $result = [[]];

        foreach ($attributeSets as $set) {
            $newResult = [];
            foreach ($result as $partial) {
                foreach ($set['values'] as $valueInfo) {
                    $combo = $partial;
                    $combo[] = [
                        'attribute' => $set['attribute'],
                        'value' => $valueInfo['entity'],
                        'definition' => $valueInfo['definition'],
                    ];
                    $newResult[] = $combo;
                }
            }
            $result = $newResult;
        }

        return $result;
    }

    private function getAttributeBlueprint(): array
    {
        return [
            [
                'slug' => 'color',
                'name' => 'Couleur',
                'type' => 'chips',
                'values' => [
                    ['slug' => 'tech-blue', 'label' => 'Bleu TechNova', 'hex' => '#1E88E5', 'priceDelta' => 0],
                    ['slug' => 'graphite', 'label' => 'Graphite', 'hex' => '#4F5B66', 'priceDelta' => 0.02],
                    ['slug' => 'sunset', 'label' => 'Orange Aurora', 'hex' => '#FF8B3D', 'priceDelta' => 0.03],
                ],
            ],
            [
                'slug' => 'edition',
                'name' => 'Edition',
                'type' => 'select',
                'values' => [
                    ['slug' => 'standard', 'label' => 'Standard', 'priceDelta' => 0],
                    ['slug' => 'pro', 'label' => 'Pro', 'priceDelta' => 0.12],
                    ['slug' => 'ultra', 'label' => 'Ultra', 'priceDelta' => 0.22],
                ],
            ],
        ];
    }

    private function generateSku(string $brandSlug): string
    {
        $prefix = strtoupper(substr($brandSlug, 0, 3));

        return sprintf('%s-%04d', $prefix, \random_int(1000, 9999));
    }

    private function generateBarcode(): string
    {
        return (string) \random_int(100000000000, 999999999999);
    }
}
