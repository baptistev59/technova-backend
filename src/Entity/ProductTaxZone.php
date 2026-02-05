<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductTaxZoneRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Product-specific tax configuration by country.
 * Allows defining different tax classes for different countries.
 * 
 * Example: 
 *   - Product "Laptop" for FR+DE+IT with class STANDARD (20%)
 *   - Same "Laptop" for GB+IE with class REDUCED (17%)
 */
#[ORM\Entity(repositoryClass: ProductTaxZoneRepository::class)]
#[ORM\Table(name: 'product_tax_zone', indexes: [
    new ORM\Index(name: 'idx_product_tax_zone_product', columns: ['product_id']),
])]
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

    /**
     * List of country codes (ISO 3166-1 alpha-2) covered by this configuration.
     * Example: ["FR", "DE", "IT"]
     * 
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    #[Assert\NotBlank]
    #[Assert\Count(min: 1, minMessage: 'Au moins un pays doit être sélectionné')]
    #[Assert\All([
        new Assert\Length(exactly: 2),
        new Assert\Regex(pattern: '/^[A-Z]{2}$/', message: 'Code pays invalide (doit être 2 lettres majuscules)')
    ])]
    private array $countryCodes = [];

    /**
     * Tax class for this product in these countries.
     * Can be different from the product's default tax class.
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

    /**
     * @return string[]
     */
    public function getCountryCodes(): array
    {
        return $this->countryCodes;
    }

    /**
     * @param string[] $countryCodes
     */
    public function setCountryCodes(array $countryCodes): self
    {
        // Normalize to uppercase
        $this->countryCodes = array_map('strtoupper', array_values($countryCodes));
        return $this;
    }

    public function hasCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), $this->countryCodes, true);
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
