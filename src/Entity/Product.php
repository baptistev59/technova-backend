<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[OA\Schema(
    schema: 'Product',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'shortDescription', type: 'string', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float'),
        new OA\Property(property: 'isPublished', type: 'boolean'),
    ],
    required: ['name', 'price', 'isPublished']
)]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRODUCT_SLUG', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Merci d’indiquer un nom de produit.')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]*$/',
        message: 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets.'
    )]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\GreaterThanOrEqual(0)]
    private ?string $price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    private ?string $promoPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $bundleDiscountPercent = null;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(0)]
    private int $stock = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, options: ['default' => '0.000'])]
    #[Assert\GreaterThanOrEqual(0)]
    private string $weight = '0.000';

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    private ?int $lowStockThreshold = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $sku = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $barcode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 32, options: ['default' => 'STANDARD'])]
    #[Assert\Choice(choices: ['STANDARD', 'REDUCED', 'ZERO'])]
    private string $taxClass = 'STANDARD';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $keywords = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isFeatured = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublished = true;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Brand $brand = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    /**
     * @var Collection<int, ProductImage>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    /**
     * @var Collection<int, ProductReview>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductReview::class, orphanRemoval: true)]
    private Collection $reviews;

    /**
     * @var Collection<int, ProductAttribute>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttribute::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributes;

    /**
     * @var Collection<int, ProductVariant>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $variants;

    /**
     * @var Collection<int, ProductAttributeSelection>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttributeSelection::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributeSelections;

    /**
     * @var Collection<int, ProductBundleItem>
     */
    #[ORM\OneToMany(mappedBy: 'bundle', targetEntity: ProductBundleItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $bundleItems;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->attributes = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->attributeSelections = new ArrayCollection();
        $this->bundleItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): float
    {
        return (float) $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = (string) $price;

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): self
    {
        $this->stock = $stock;

        return $this;
    }

    public function getWeight(): float
    {
        return (float) $this->weight;
    }

    public function setWeight(float $weight): self
    {
        $this->weight = number_format($weight, 3, '.', '');

        return $this;
    }

    public function getLowStockThreshold(): ?int
    {
        return $this->lowStockThreshold;
    }

    public function setLowStockThreshold(?int $lowStockThreshold): self
    {
        $this->lowStockThreshold = $lowStockThreshold;

        return $this;
    }

    public function getPromoPrice(): ?float
    {
        return null !== $this->promoPrice ? (float) $this->promoPrice : null;
    }

    public function setPromoPrice(?float $promoPrice): self
    {
        $this->promoPrice = null !== $promoPrice ? (string) $promoPrice : null;

        return $this;
    }

    public function getBundleDiscountPercent(): ?float
    {
        return null !== $this->bundleDiscountPercent ? (float) $this->bundleDiscountPercent : null;
    }

    public function setBundleDiscountPercent(?float $bundleDiscountPercent): self
    {
        $this->bundleDiscountPercent = null !== $bundleDiscountPercent ? (string) $bundleDiscountPercent : null;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): self
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTaxClass(): string
    {
        return $this->taxClass;
    }

    public function setTaxClass(string $taxClass): self
    {
        $this->taxClass = strtoupper($taxClass);

        return $this;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): self
    {
        $this->shop = $shop;

        return $this;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getPrimaryImage(array $visited = []): ?ProductImage
    {
        $id = $this->getId();
        if (null !== $id && in_array($id, $visited, true)) {
            return null;
        }

        if (null !== $id) {
            $visited[] = $id;
        }

        foreach ($this->images as $image) {
            if ($image->isMain()) {
                return $image;
            }
        }

        $firstImage = $this->images->first();
        if ($firstImage) {
            return $firstImage;
        }

        if ('grouped' !== $this->type) {
            return null;
        }

        foreach ($this->bundleItems as $item) {
            $component = $item->getComponent();
            if (!$component || $component === $this) {
                continue;
            }

            $componentImage = $component->getPrimaryImage($visited);
            if ($componentImage) {
                return $componentImage;
            }
        }

        return null;
    }

    public function addImage(ProductImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        if ($this->images->removeElement($image) && $image->getProduct() === $this) {
            $image->setProduct(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductReview>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(ProductReview $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProduct($this);
        }

        return $this;
    }

    public function removeReview(ProductReview $review): self
    {
        if ($this->reviews->removeElement($review) && $review->getProduct() === $this) {
            $review->setProduct(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductAttribute>
     */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(ProductAttribute $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setProduct($this);
        }

        return $this;
    }

    public function removeAttribute(ProductAttribute $attribute): self
    {
        if ($this->attributes->removeElement($attribute) && $attribute->getProduct() === $this) {
            $attribute->setProduct(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(ProductVariant $variant): self
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }

        return $this;
    }

    public function removeVariant(ProductVariant $variant): self
    {
        if ($this->variants->removeElement($variant) && $variant->getProduct() === $this) {
            $variant->setProduct(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductAttributeSelection>
     */
    public function getAttributeSelections(): Collection
    {
        return $this->attributeSelections;
    }

    public function addAttributeSelection(ProductAttributeSelection $selection): self
    {
        if (!$this->attributeSelections->contains($selection)) {
            $this->attributeSelections->add($selection);
            $selection->setProduct($this);
        }

        return $this;
    }

    public function removeAttributeSelection(ProductAttributeSelection $selection): self
    {
        if ($this->attributeSelections->removeElement($selection) && $selection->getProduct() === $this) {
            $selection->setProduct(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductBundleItem>
     */
    public function getBundleItems(): Collection
    {
        return $this->bundleItems;
    }

    public function addBundleItem(ProductBundleItem $item): self
    {
        if (!$this->bundleItems->contains($item)) {
            $this->bundleItems->add($item);
            $item->setBundle($this);
        }

        return $this;
    }

    public function removeBundleItem(ProductBundleItem $item): self
    {
        if ($this->bundleItems->removeElement($item) && $item->getBundle() === $this) {
            $item->setBundle(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
