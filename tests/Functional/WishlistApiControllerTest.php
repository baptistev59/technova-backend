<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use App\Entity\Wishlist;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class WishlistApiControllerTest extends WebTestCase
{
    private const TEST_PASSWORD = 'Test#1234';

    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $container = self::getContainer();

        $this->manager = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        
        // Ne pas utiliser de transaction - laisser les données en base pour JWT
    }

    protected function tearDown(): void
    {
        if ($this->manager->isOpen()) {
            // Nettoyer la base de données sans rollback
            $connection = $this->manager->getConnection();
            try {
                $connection->executeStatement('TRUNCATE TABLE wishlist, customer_order_item, customer_order, message, conversation, order_document, product_image, product_variant, product_attribute_value, product_attribute, product, media, shop, vendor, "user" RESTART IDENTITY CASCADE');
            } catch (\Throwable $e) {
                // Ignorer les erreurs de truncate
            }
            $this->manager->close();
        }

        parent::tearDown();
    }

    public function testListFavoritesReturnsUsersItems(): void
    {
        $context = $this->createWishlistContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->authHeaders($token);

        $this->client->request('GET', '/api/wishlists', [], [], $headers);

        self::assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['count']);
        self::assertCount(1, $payload['items']);
        self::assertSame($context['product']->getId(), $payload['items'][0]['product']['id']);
    }

    public function testAddFavoritesReturnsCreatedAndPreventsDuplicates(): void
    {
        $user = $this->createUser('wishlist-add@technova.test');
        $product = $this->createProduct();
        $this->manager->flush();

        $token = $this->jwtManager->create($user);
        $headers = $this->authHeaders($token);

        $body = ['productId' => $product->getId()];

        $this->client->request(
            'POST',
            '/api/wishlists',
            [],
            [],
            array_merge($headers, ['CONTENT_TYPE' => 'application/json']),
            json_encode($body)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $created = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('added', $created['status']);
        self::assertIsInt($created['wishlistId']);

        $this->client->request(
            'POST',
            '/api/wishlists',
            [],
            [],
            array_merge($headers, ['CONTENT_TYPE' => 'application/json']),
            json_encode($body)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testDeleteFavoritesRemovesEntryAndRespectsOwnership(): void
    {
        $context = $this->createWishlistContext();
        $token = $this->jwtManager->create($context['user']);
        $headers = $this->authHeaders($token);

        $wishlistId = $context['wishlist']->getId();
        $this->client->request('DELETE', '/api/wishlists/'.$wishlistId, [], [], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $deleted = $this->manager->getRepository(Wishlist::class)->find($wishlistId);
        self::assertNull($deleted);

        $otherUser = $this->createUser('wishlist-other@technova.test');
        $otherWishlist = $this->createWishlist($otherUser, $context['product']);
        $this->manager->flush();

        $otherWishlistId = $otherWishlist->getId();
        $this->client->request('DELETE', '/api/wishlists/'.$otherWishlistId, [], [], $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createWishlistContext(): array
    {
        $user = $this->createUser('wishlist-client@technova.test');
        $product = $this->createProduct();
        $this->manager->flush();

        $wishlist = $this->createWishlist($user, $product);
        $this->manager->flush();

        return [
            'user' => $user,
            'product' => $product,
            'wishlist' => $wishlist,
        ];
    }

    private function createUser(string $email, array $roles = ['ROLE_USER']): User
    {
        $user = (new User())
            ->setEmail(uniqid('wishlist-user-').$email)
            ->setRoles($roles)
            ->setFirstname('Tech')
            ->setLastname('Nova');

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, self::TEST_PASSWORD)
        );

        $this->manager->persist($user);

        return $user;
    }

    private function createProduct(): Product
    {
        $vendorUser = $this->createUser('wishlist-vendor@technova.test', ['ROLE_VENDOR']);

        $vendor = (new Vendor())
            ->setCompanyName('Favoris Vendor '.uniqid())
            ->setEmail('vendor-wishlist@technova.test')
            ->setOwner($vendorUser);

        $this->manager->persist($vendor);

        $shop = (new Shop())
            ->setName('Favoris Shop '.uniqid())
            ->setSlug('favoris-shop-'.uniqid())
            ->setContactEmail('vendor-shop@technova.test')
            ->setOwner($vendor);

        $this->manager->persist($shop);

        $category = (new Category())
            ->setName('Favoris Category '.uniqid())
            ->setSlug('favoris-category-'.uniqid());

        $this->manager->persist($category);

        $product = (new Product())
            ->setName('Wishlist Product '.uniqid())
            ->setSlug('wishlist-product-'.uniqid())
            ->setPrice(19.90)
            ->setStock(10)
            ->setCategory($category)
            ->setShop($shop);

        $this->manager->persist($product);

        return $product;
    }

    private function createWishlist(User $user, Product $product): Wishlist
    {
        $wishlist = (new Wishlist())
            ->setUser($user)
            ->setProduct($product);

        $this->manager->persist($wishlist);

        return $wishlist;
    }

    private function authHeaders(string $token): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];
    }
}
