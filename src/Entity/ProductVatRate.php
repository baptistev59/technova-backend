<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductVatRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * ProductVatRate: Sélection du taux TVA pour un produit dans un pays spécifique.
 * 
 * Contrainte: UN SEUL taux par produit/pays
 * 
 * Exemple:
 *   Produit "Livre Python" en France → REDUCED (5.5%)
 *   Produit "Livre Python" en Allemagne → REDUCED (7%)
 *   Produit "Souris" en France → STANDARD (20%)
 */
#[ORM\Entity(repositoryClass: ProductVatRateRepository::class)]
#[ORM\Table(name: 'product_vat_rate')]
#[ORM\UniqueConstraint(name: 'uniq_product_country', columns: ['product_id', 'country_code'])]
#[ORM\Index(name: 'idx_product_vat_rate_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_product_vat_rate_country', columns: ['country_code'])]
#[ORM\HasLifecycleCallbacks]
class ProductVatRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productVatRates')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    /**
     * Code pays ISO 3166-1 alpha-2 (ex: "FR", "DE", "IT")
     */
    #[ORM\Column(length: 2)]
    private string $countryCode = '';

    /**
     * FK vers VatRate: le taux sélectionné pour ce produit dans ce pays
     * Important: C'est une référence, pas une copie du taux
     */
    #[ORM\ManyToOne(targetEntity: VatRate::class)]
    #[ORM\JoinColumn(name: 'vat_rate_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?VatRate $vatRate = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = strtoupper($countryCode);
        return $this;
    }

    public function getVatRate(): ?VatRate
    {
        return $this->vatRate;
    }

    public function setVatRate(?VatRate $vatRate): self
    {
        $this->vatRate = $vatRate;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
