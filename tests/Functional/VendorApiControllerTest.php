<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\CustomerOrder;
use App\Entity\CustomerOrderItem;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class VendorApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $passwordHasher;
    private array $uploadedPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->manager = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        try {
            $this->manager->beginTransaction();
        } catch (ConnectionException $exception) {
            static::markTestSkipped('Base de données indisponible (configurez DATABASE_URL pour les tests). ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        foreach ($this->uploadedPaths as $path) {
            $absolute = $projectDir . '/public/' . ltrim($path, '/');
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        if ($this->manager->isOpen()) {
            $connection = $this->manager->getConnection();
            if ($connection->isTransactionActive()) {
                $this->manager->rollback();
            }
            $this->manager->close();
        }

        parent::tearDown();
    }

    public function testMediaUploadReturnsMetadata(): void
    {
        $context = $this->createVendorContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->prepareAuthHeaders($token);

        $temp = $this->createTemporaryImage();
        $file = new UploadedFile($temp, 'sample.png', 'image/png', null, true);
        $this->client->request(
            'POST',
            '/api/vendor/media',
            ['profile' => 'shop_banner'],
            ['file' => $file],
            $headers
        );
        self::assertSame($headers['HTTP_AUTHORIZATION'], $this->client->getRequest()->headers->get('Authorization'));
        @unlink($temp);

        self::assertResponseStatusCodeSame(201);
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $payload);
        self::assertIsInt($payload['id']);
        self::assertSame('shop_banner', $payload['profile']);
        self::assertSame(1920, $payload['width']);
        self::assertSame(1080, $payload['height']);
        self::assertSame('image/webp', $payload['mimeType']);
        self::assertIsString($payload['path']);
        $media = $this->manager->getRepository(Media::class)->find($payload['id']);
        self::assertNotNull($media);
        self::assertSame($payload['path'], $media->getPath());
        self::assertSame($payload['profile'], $media->getProfile());
        $this->uploadedPaths[] = $payload['path'];
    }

    public function testCreateShopPersistsNewShop(): void
    {
        $context = $this->createVendorWithoutShop();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->prepareAuthHeaders($token);

        $form = [
            'name' => 'Postman Vendor Shop',
            'slug' => 'postman-test-shop',
            'description' => 'Échantillon de boutique via API.',
            'policies' => 'Livraison express, retours 30 jours.',
            'contactEmail' => 'vendor-shop@technova.test',
        ];

        $this->client->request(
            'POST',
            '/api/vendor/shop',
            $form,
            [],
            $headers
        );

        self::assertSame($headers['HTTP_AUTHORIZATION'], $this->client->getRequest()->headers->get('Authorization'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertTrue($this->client->getResponse()->headers->has('Location'));

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($form['name'], $payload['name']);
        self::assertSame($form['policies'], $payload['policies']);
        self::assertSame($form['contactEmail'], $payload['contactEmail']);
        self::assertSame($form['slug'], $payload['slug']);
    }

    public function testUpdateShopAppliesPartialPayload(): void
    {
        $context = $this->createVendorContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->prepareAuthHeaders($token);

        $update = [
            'name' => 'Functional Shop Updated',
            'policies' => 'Conditions revues 2025.',
        ];

        $this->client->request(
            'PATCH',
            '/api/vendor/shop',
            $update,
            [],
            $headers
        );

        self::assertSame($headers['HTTP_AUTHORIZATION'], $this->client->getRequest()->headers->get('Authorization'));
        self::assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($update['name'], $payload['name']);
        self::assertSame($update['policies'], $payload['policies']);
    }

    public function testUpdateProfileReturnsSerializedVendor(): void
    {
        $context = $this->createVendorContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->prepareAuthHeaders($token);

        $payload = [
            'companyName' => 'Functional Vendor Updated',
            'phone' => '+33123456789',
            'email' => 'updated-vendor@technova.test',
        ];

        $this->client->request(
            'PATCH',
            '/api/vendor/profile',
            [],
            [],
            array_merge($headers, ['CONTENT_TYPE' => 'application/json']),
            json_encode($payload)
        );

        self::assertSame($headers['HTTP_AUTHORIZATION'], $this->client->getRequest()->headers->get('Authorization'));
        self::assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($payload['companyName'], $response['companyName']);
        self::assertSame($payload['phone'], $response['phone']);
        self::assertSame($payload['email'], $response['email']);
    }

    public function testListOrdersIncludesShopItems(): void
    {
        $context = $this->createVendorContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->prepareAuthHeaders($token);

        $this->client->request(
            'GET',
            '/api/vendor/orders',
            [],
            [],
            $headers
        );
        self::assertSame($headers['HTTP_AUTHORIZATION'], $this->client->getRequest()->headers->get('Authorization'));

        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('items', $payload);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);
        self::assertSame(CustomerOrder::STATUS_PENDING, $payload['items'][0]['status']);
    }

    public function testChangeOrderStatusUpdatesState(): void
    {
        $context = $this->createVendorContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->prepareAuthHeaders($token);
        $orderId = $context['order']->getId();

        $this->client->request(
            'PATCH',
            '/api/vendor/orders/'.$orderId.'/status',
            [],
            [],
            array_merge($headers, ['CONTENT_TYPE' => 'application/json']),
            json_encode(['status' => CustomerOrder::STATUS_PAID])
        );
        self::assertSame($headers['HTTP_AUTHORIZATION'], $this->client->getRequest()->headers->get('Authorization'));

        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(CustomerOrder::STATUS_PAID, $payload['status']);
    }

    public function testGenerateDocumentReturnsBase64(): void
    {
        $context = $this->createVendorContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->prepareAuthHeaders($token);
        $orderId = $context['order']->getId();

        $this->client->request(
            'POST',
            '/api/vendor/orders/'.$orderId.'/documents',
            ['type' => 'invoice'],
            [],
            $headers
        );
        self::assertResponseStatusCodeSame(201);
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invoice', $payload['type']);
        self::assertArrayHasKey('url', $payload);
        self::assertArrayHasKey('hash', $payload);
        self::assertStringContainsString('/uploads/documents/', $payload['url']);

        $this->client->request(
            'GET',
            '/api/vendor/orders/'.$orderId.'/documents',
            [],
            [],
            $headers
        );
        self::assertResponseIsSuccessful();
        $list = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($list);
        self::assertSame($payload['id'], $list[0]['id']);
    }

    private function createVendorContext(): array
    {
        $user = (new User())
            ->setEmail('functional-vendor@technova.test')
            ->setFirstname('Functional')
            ->setLastname('Vendor')
            ->setRoles(['ROLE_VENDOR']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Vendor#00'));
        $this->manager->persist($user);

        $vendor = (new Vendor())
            ->setCompanyName('Functional Vendor')
            ->setEmail('vendor@functional.test')
            ->setOwner($user);
        $this->manager->persist($vendor);

        $shop = (new Shop())
            ->setName('Functional Shop')
            ->setSlug('functional-shop-'.uniqid())
            ->setContactEmail('shop@functional.test')
            ->setOwner($vendor);
        $this->manager->persist($shop);

        $category = (new Category())
            ->setName('Functional Category')
            ->setSlug('functional-category-'.uniqid())
            ->setDescription('Test category')
            ->setIconPath('/images/categories/default.svg');
        $this->manager->persist($category);

        $product = (new Product())
            ->setName('Functional Product '.uniqid())
            ->setSlug('functional-product-'.uniqid())
            ->setShortDescription('Desc')
            ->setPrice(149.99)
            ->setStock(10)
            ->setCategory($category)
            ->setShop($shop);
        $this->manager->persist($product);
        $this->manager->flush();

        $order = (new CustomerOrder())
            ->setReference('ORDER-'.uniqid())
            ->setStatus(CustomerOrder::STATUS_PENDING)
            ->setCurrency('EUR')
            ->setTotalAmount((string) $product->getPrice())
            ->setShippingAddress(['line1' => 'Rue de Test'])
            ->setBillingAddress(['line1' => 'Rue de Test'])
            ->setOwner($user);
        $this->manager->persist($order);

        $item = (new CustomerOrderItem())
            ->setProductId($product->getId())
            ->setProductName($product->getName())
            ->setQuantity(1)
            ->setUnitPrice((string) $product->getPrice())
            ->setLineTotal((string) $product->getPrice())
        ;
        $order->addItem($item);
        $this->manager->persist($item);
        $this->manager->persist($item);
        $this->manager->flush();

        return [
            'user' => $user,
            'vendor' => $vendor,
            'shop' => $shop,
            'product' => $product,
            'order' => $order,
        ];
    }

    private function createVendorWithoutShop(): array
    {
        $user = (new User())
            ->setEmail('functional-vendor-create@technova.test')
            ->setFirstname('Functional')
            ->setLastname('VendorCreate')
            ->setRoles(['ROLE_VENDOR']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Vendor#00'));
        $this->manager->persist($user);

        $vendor = (new Vendor())
            ->setCompanyName('Vendor Creation')
            ->setEmail('vendor-create@functional.test')
            ->setOwner($user);
        $this->manager->persist($vendor);
        $this->manager->flush();

        return ['user' => $user, 'vendor' => $vendor];
    }

    private function createTemporaryImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tn-test');
        $image = imagecreatetruecolor(1, 1);
        $bg = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bg);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function prepareAuthHeaders(string $token): array
    {
        self::assertNotEmpty($token, 'Le JWT doit être généré.');
        $header = 'Bearer '.$token;
        return ['HTTP_AUTHORIZATION' => $header];
    }
}
