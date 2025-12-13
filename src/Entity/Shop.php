<?php

namespace App\Entity;

use App\Entity\Product;
use App\Entity\AttributeDefinition;
use App\Entity\Traits\Timestampable;
use App\Entity\Vendor;
use App\Repository\ShopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopRepository::class)]
#[ORM\Table(name: 'shop', uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_SHOP_SLUG', columns: ['slug'])])]
#[ORM\HasLifecycleCallbacks]
class Shop
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $banner = null;

    #[ORM\Column(length: 255)]
    private ?string $contactEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $policies = null;

    #[ORM\ManyToOne(inversedBy: 'shops')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vendor $owner = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: Product::class, orphanRemoval: true)]
    private Collection $products;

    /**
     * @var Collection<int, AttributeDefinition>
     */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: AttributeDefinition::class, orphanRemoval: true)]
    private Collection $attributeDefinitions;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->attributeDefinitions = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): self
    {
        $this->banner = $banner;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getPolicies(): ?string
    {
        return $this->policies;
    }

    public function setPolicies(?string $policies): self
    {
        $this->policies = $policies;

        return $this;
    }

    public function getOwner(): ?Vendor
    {
        return $this->owner;
    }

    public function setOwner(?Vendor $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setShop($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        if ($this->products->removeElement($product) && $product->getShop() === $this) {
            $product->setShop(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, AttributeDefinition>
     */
    public function getAttributeDefinitions(): Collection
    {
        return $this->attributeDefinitions;
    }

    public function addAttributeDefinition(AttributeDefinition $attributeDefinition): self
    {
        if (!$this->attributeDefinitions->contains($attributeDefinition)) {
            $this->attributeDefinitions->add($attributeDefinition);
            $attributeDefinition->setShop($this);
        }

        return $this;
    }

    public function removeAttributeDefinition(AttributeDefinition $attributeDefinition): self
    {
        if ($this->attributeDefinitions->removeElement($attributeDefinition) && $attributeDefinition->getShop() === $this) {
            $attributeDefinition->setShop(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
