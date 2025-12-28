<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\CustomerOrder;
use App\Entity\CustomerOrderItem;
use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ConversationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    /* ============================================================
     * TESTS
     * ============================================================
     */

    public function testVendorConversationEndpoints(): void
    {
        $context = $this->createVendorOrderContext();

        $token = $this->jwtManager->create($context['vendorUser']);
        $headers = $this->authHeaders($token);
        $orderId = $context['order']->getId();

        // POST message vendeur
        $this->client->request(
            'POST',
            "/api/vendor/conversations/{$orderId}/messages",
            ['content' => 'Bonjour depuis le vendeur'],
            [],
            $headers
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // GET conversation vendeur
        $this->client->request(
            'GET',
            "/api/vendor/conversations/{$orderId}",
            [],
            [],
            $headers
        );

        self::assertResponseIsSuccessful();

        $payload = json_decode(
            $this->client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertSame($orderId, $payload['orderId']);
        self::assertCount(1, $payload['messages']);
        self::assertSame('Bonjour depuis le vendeur', $payload['messages'][0]['content']);
    }

    public function testClientConversationEndpoints(): void
    {
        $context = $this->createClientOrderContext();

        $token = $this->jwtManager->create($context['client']);
        $headers = $this->authHeaders($token);
        $orderId = $context['order']->getId();

        // POST message client
        $this->client->request(
            'POST',
            "/api/account/conversations/{$orderId}/messages",
            ['content' => 'Question client'],
            [],
            $headers
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // GET conversation client
        $this->client->request(
            'GET',
            "/api/account/conversations/{$orderId}",
            [],
            [],
            $headers
        );

        self::assertResponseIsSuccessful();

        $payload = json_decode(
            $this->client->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertSame($orderId, $payload['orderId']);
        self::assertCount(1, $payload['messages']);
        self::assertSame('Question client', $payload['messages'][0]['content']);
    }

    /* ============================================================
     * CONTEXT BUILDERS
     * ============================================================
     */

    private function createVendorOrderContext(): array
    {
        $vendorUser = $this->createUser('vendor@technova.test', ['ROLE_VENDOR']);
        $vendor = (new Vendor())
            ->setCompanyName('Vendor Messagerie')
            ->setEmail('vendor@technova.test')
            ->setOwner($vendorUser);

        $this->em->persist($vendor);

        $shop = $this->createShop($vendor, 'Shop Vendeur');

        $clientUser = $this->createUser('client@technova.test', ['ROLE_USER']);

        $product = $this->createProduct($shop);

        $order = $this->createOrder($clientUser, $product);

        $this->em->flush();

        return [
            'vendorUser' => $vendorUser,
            'order' => $order,
        ];
    }

    private function createClientOrderContext(): array
    {
        $vendorUser = $this->createUser('vendor-client@technova.test', ['ROLE_VENDOR']);
        $vendor = (new Vendor())
            ->setCompanyName('Vendor Client')
            ->setEmail('vendor-client@technova.test')
            ->setOwner($vendorUser);

        $this->em->persist($vendor);

        $shop = $this->createShop($vendor, 'Shop Client');

        $client = $this->createUser('client-dialogue@technova.test', ['ROLE_USER']);

        $product = $this->createProduct($shop);

        $order = $this->createOrder($client, $product);

        $this->em->flush();

        return [
            'client' => $client,
            'order' => $order,
        ];
    }

    /* ============================================================
     * ENTITY FACTORIES
     * ============================================================
     */

    private function createUser(string $email, array $roles): User
    {
        $user = (new User())
            ->setEmail(uniqid('', true).'_'.$email)
            ->setRoles($roles)
            ->setFirstname('Tech')
            ->setLastname('Nova');

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'Test#1234')
        );

        $this->em->persist($user);

        return $user;
    }

    private function createShop(Vendor $vendor, string $name): Shop
    {
        $shop = (new Shop())
            ->setName($name)
            ->setSlug(strtolower(str_replace(' ', '-', $name)).'-'.uniqid())
            ->setContactEmail('shop@technova.test')
            ->setOwner($vendor);

        $this->em->persist($shop);

        return $shop;
    }

    private function createProduct(Shop $shop): Product
    {
        $category = (new Category())
            ->setName('Cat Test')
            ->setSlug('cat-test-'.uniqid());

        $this->em->persist($category);

        $product = (new Product())
            ->setName('Produit Test')
            ->setSlug('produit-test-'.uniqid())
            ->setPrice(99.99)
            ->setStock(10)
            ->setCategory($category)
            ->setShop($shop);

        $this->em->persist($product);

        return $product;
    }

    private function createOrder(User $client, Product $product): CustomerOrder
    {
        // ⚠️ IMPORTANT : s'assurer que le produit a un ID
        if (null === $product->getId()) {
            $this->em->flush();
        }

        $order = (new CustomerOrder())
            ->setOwner($client)
            ->setReference('ORD-'.uniqid())
            ->setTotalAmount('99.99')
            ->setCurrency('EUR')
            ->setShippingAddress(['line1' => 'Rue du test']);

        $this->em->persist($order);

        $item = (new CustomerOrderItem())
            ->setCustomerOrder($order)
            ->setProductId($product->getId()) // ✅ int garanti
            ->setProductName($product->getName())
            ->setShopId($product->getShop()?->getId())
            ->setQuantity(1)
            ->setUnitPrice('99.99')
            ->setLineTotal('99.99');

        $order->addItem($item);
        $this->em->persist($item);

        return $order;
    }

    /* ============================================================
     * HELPERS
     * ============================================================
     */

    private function authHeaders(string $token): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];
    }
}
