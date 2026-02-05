<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductTaxZoneRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Junction table: Product can belong to multiple TaxZones
 * Each zone can have a different tax class for the same product
 * 
 * Example: 
 *   - Product "Laptop" in Zone EU with class STANDARD (20%)
 *   - Same "Laptop" in Zone UK/IE with class REDUCED (17%)
 */
#[ORM\Entity(repositoryClass: ProductTaxZoneRepository::class)]
#[ORM\Table(name: 'product_tax_zone', indexes: [
    new ORM\Index(name: 'idx_product_tax_zone_product', columns: ['product_id']),
    new ORM\Index(name: 'idx_product_tax_zone_zone', columns: ['tax_zone_id']),
])]
#[ORM\UniqueConstraint(
    name: 'uniq_product_tax_zone_product_zone',
    columns: ['product_id', 'tax_zone_id']
)]
#[ORM\HasLifecycleCallbacks]
class ProductTaxZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productTaxZones')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: TaxZone::class)]
    #[ORM\JoinColumn(name: 'tax_zone_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TaxZone $taxZone = null;

    /**
     * Tax class for this product in this specific zone
     * Can be different from the product's default tax class
     */
    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['STANDARD', 'REDUCED', 'ZERO'])]
    private string $taxClass = 'STANDARD';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getTaxZone(): ?TaxZone
    {
        return $this->taxZone;
    }

    public function setTaxZone(?TaxZone $taxZone): self
    {
        $this->taxZone = $taxZone;
        return $this;
    }

    public function getTaxClass(): string
    {
        return $this->taxClass;
    }

    public function setTaxClass(string $taxClass): self
    {
        $this->taxClass = $taxClass;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
