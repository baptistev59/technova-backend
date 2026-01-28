<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Address;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\SavedCart;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use App\Entity\ShippingMethod;
use App\Entity\ShippingZone;
use App\Entity\ShippingRate;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CheckoutControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $passwordHasher;
    private string $token = '';
    private User $user;
    private Address $address;
    private Shop $shop;
    private Product $product;
    private ShippingMethod $shippingMethod;
    private static int $testCounter = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $container->set(StripePaymentService::class, new class {
            /**
             * @return array{id:string,url:string}
             */
            public function createCheckoutSession($order, string $successUrl, string $cancelUrl): array
            {
                return ['id' => 'sess_mock', 'url' => 'https://example.com/mock-checkout'];
            }
        });
        $this->manager = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        self::$testCounter++;
        
        $this->setupFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->manager->isOpen()) {
            $connection = $this->manager->getConnection();
            try {
                $connection->executeStatement('TRUNCATE TABLE "customer_order" CASCADE');
                $connection->executeStatement('TRUNCATE TABLE "product" CASCADE');
                $connection->executeStatement('TRUNCATE TABLE "shop" CASCADE');
                $connection->executeStatement('TRUNCATE TABLE "address" CASCADE');
                $connection->executeStatement('TRUNCATE TABLE "user" CASCADE');
            } catch (\Exception $e) {
                // Ignore si tables n'existent pas
            }
        }
        
        parent::tearDown();
    }

    private function setupFixtures(): void
    {
        // Créer un utilisateur
        $this->user = new User();
        $this->user->setEmail('checkout@example.com');
        $this->user->setFirstname('John');
        $this->user->setLastname('Doe');
        $this->user->setRoles(['ROLE_USER']);
        $this->user->setIsEmailVerified(true);
        $password = $this->passwordHasher->hashPassword($this->user, 'password123');
        $this->user->setPassword($password);

        $this->manager->persist($this->user);

        // Créer une adresse pour l'utilisateur
        $this->address = new Address();
        $this->address->setOwner($this->user);
        $this->address->setLabel('Domicile');
        $this->address->setAddressLine1('123 Rue de la Paix');
        $this->address->setAddressLine2('Apt 42');
        $this->address->setCity('Paris');
        $this->address->setPostalCode('75001');
        $this->address->setState('Île-de-France');
        $this->address->setCountry('FR');
        $this->address->setIsDefault(true);
        $this->address->setIsShipping(true);
        $this->address->setIsBilling(true);

        $this->manager->persist($this->address);
        $this->user->addAddress($this->address);

        // Créer une boutique (requise pour les produits)
        $vendor = new Vendor();
        $vendor->setCompanyName('Test Vendor');
        $vendor->setEmail('vendor@example.com');
        
        $this->manager->persist($vendor);

        $this->shop = new Shop();
        $this->shop->setName('Test Shop');
        $this->shop->setSlug('test-shop');
        $this->shop->setContactEmail('shop@example.com');
        $this->shop->setOwner($vendor);

        $this->manager->persist($this->shop);

        // Créer une catégorie (requise pour les produits)
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $this->manager->persist($category);

        // Créer un produit avec prix
        $this->product = new Product();
        $this->product->setName('Test Product');
        $this->product->setSlug('test-product');
        $this->product->setShop($this->shop);
        $this->product->setCategory($category);
        $this->product->setPrice(99.99);
        $this->product->setStock(10);
        $this->product->setDescription('Product for testing');

        $this->manager->persist($this->product);

        // Créer une zone et un mode de livraison avec tarif
        $zone = new ShippingZone();
        $zone->setShop($this->shop);
        $zone->setName('France');
        $zone->setCountries(['FR']);
        $this->manager->persist($zone);

        $this->shippingMethod = new ShippingMethod();
        $this->shippingMethod->setShop($this->shop);
        $this->shippingMethod->setZone($zone);
        $this->shippingMethod->setName('Standard');
        $this->shippingMethod->setCarrierName('Test Carrier');
        $this->shippingMethod->setMinDays(2);
        $this->shippingMethod->setMaxDays(4);
        $this->manager->persist($this->shippingMethod);

        $rate = new ShippingRate();
        $rate->setMethod($this->shippingMethod);
        $rate->setMinWeight(0.0);
        $rate->setMaxWeight(null);
        $rate->setPrice(5.0);
        $this->manager->persist($rate);
        $this->manager->flush();

        // Créer le JWT directement
        $this->token = $this->jwtManager->create($this->user);
    }
    
    private function addProductToCart(Product $product, int $quantity = 1): void
    {
        $savedCart = $this->manager->getRepository(SavedCart::class)->findOneBy(['owner' => $this->user]);

        if (!$savedCart) {
            $savedCart = (new SavedCart())
                ->setOwner($this->user)
                ->setItems(['version' => 3, 'lines' => []])
                ->setUpdatedAt(new \DateTimeImmutable());
            $this->manager->persist($savedCart);
        }

        $items = $savedCart->getItems();
        $lineKey = $product->getId() . '_';
        $items['lines'][$lineKey] = [
            'product_id' => $product->getId(),
            'variant_id' => null,
            'quantity' => $quantity,
            'unit_price_override' => null,
        ];

        $savedCart->setItems($items);
        $savedCart->setUpdatedAt(new \DateTimeImmutable());
        $this->manager->flush();
        
        // Vérifier que le panier contient bien une ligne
        $this->client->request('GET', '/api/cart', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);
        
        self::assertResponseIsSuccessful();
        
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertGreaterThan(0, $payload['totalQuantity'] ?? 0, 'Le panier devrait contenir au moins un article après ajout.');
    }

    private function loginAndGetJwt(string $email, string $password): string
    {
        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'email' => $email,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));

        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        return $response['token'] ?? '';
    }

    public function testCheckoutRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'shipping' => ['1' => 3],
            'successUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCheckoutWithEmptyCart(): void
    {
        // NE PAS ajouter de produit - le panier reste vide

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'shipping' => ['1' => 3],
            'successUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('vide', strtolower($response['message'] ?? ''));
    }

    public function testCheckoutWithoutShippingSelection(): void
    {
        $this->addProductToCart($this->product);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'successUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('livraison', strtolower($response['message'] ?? ''));
    }

    public function testCheckoutWithInvalidAddressId(): void
    {
        $this->addProductToCart($this->product);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'addressId' => 99999, // Adresse inexistante
            'shipping' => [$this->shop->getId() => $this->shippingMethod->getId()],
            'successUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('checkoutUrl', $response);
    }

    public function testCheckoutWithAddressFromAnotherUser(): void
    {
        $this->addProductToCart($this->product);

        // Créer un autre utilisateur avec adresse
        $otherUser = new User();
        $otherUser->setEmail('other@example.com');
        $otherUser->setFirstname('Jane');
        $otherUser->setLastname('Doe');
        $otherUser->setRoles(['ROLE_USER']);
        $otherUser->setIsEmailVerified(true);
        $password = $this->passwordHasher->hashPassword($otherUser, 'password123');
        $otherUser->setPassword($password);

        $otherAddress = new Address();
        $otherAddress->setOwner($otherUser);
        $otherAddress->setLabel('Other Home');
        $otherAddress->setAddressLine1('456 Rue Autre');
        $otherAddress->setCity('Lyon');
        $otherAddress->setPostalCode('69000');
        $otherAddress->setCountry('FR');
        $otherAddress->setIsDefault(true);
        $otherAddress->setIsShipping(true);
        $otherAddress->setIsBilling(true);

        $this->manager->persist($otherUser);
        $this->manager->persist($otherAddress);
        $this->manager->flush();

        // Essayer d'utiliser l'adresse d'un autre utilisateur
        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'addressId' => $otherAddress->getId(),
            'shipping' => [$this->shop->getId() => $this->shippingMethod->getId()],
            'successUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCheckoutWithInvalidShippingMethod(): void
    {
        $this->addProductToCart($this->product);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'addressId' => $this->address->getId(),
            'shipping' => [$this->shop->getId() => 999999], // Méthode inexistante
            'successUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCheckoutCreatesOrderWithValidData(): void
    {
        $this->addProductToCart($this->product);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            'addressId' => $this->address->getId(),
            'shipping' => [$this->shop->getId() => $this->shippingMethod->getId()],
            'successUrl' => 'https://example.com/success?session_id={CHECKOUT_SESSION_ID}',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        
        self::assertArrayHasKey('checkoutUrl', $response);
        self::assertArrayHasKey('order', $response);
        self::assertArrayHasKey('id', $response['order']);
        self::assertArrayHasKey('reference', $response['order']);
        self::assertArrayHasKey('total', $response['order']);
        
        // URL Stripe mockée
        self::assertIsString($response['checkoutUrl']);
    }

    public function testCheckoutUsesDefaultAddressWhenNoneProvided(): void
    {
        $this->addProductToCart($this->product);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR' => '127.0.0.' . self::$testCounter,
        ], json_encode([
            // Pas d'addressId fourni - doit utiliser l'adresse par défaut
                'shipping' => [$this->shop->getId() => $this->shippingMethod->getId()],
            'successUrl' => 'https://example.com/success?session_id={CHECKOUT_SESSION_ID}',
            'cancelUrl' => 'https://example.com/cancel',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        
        self::assertArrayHasKey('checkoutUrl', $response);
        self::assertArrayHasKey('order', $response);
    }
}
