<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TaxZoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxZoneRepository::class)]
#[ORM\Table(name: 'tax_zone', indexes: [
    new ORM\Index(name: 'idx_tax_zone_shop', columns: ['shop_id']),
    new ORM\Index(name: 'idx_tax_zone_is_preset', columns: ['is_preset']),
])]
#[ORM\HasLifecycleCallbacks]
class TaxZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Shop $shop = null;

    #[ORM\Column(length: 64)]
    private string $code = ''; // ex: EU_STANDARD, CUSTOM_2025

    #[ORM\Column(length: 255)]
    private string $name = ''; // ex: Union Européenne — Standard

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON)]
    private array $countryCodes = []; // ex: ['FR', 'DE', 'BE', ...]

    #[ORM\Column(length: 32)]
    private string $taxClass = 'STANDARD'; // STANDARD, REDUCED, ZERO

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $rate = '0.00'; // ex: 20.00

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isPreset = false; // true si prédéfinie (non modifiable)

    #[ORM\Column(type: Types::INTEGER)]
    private int $sortOrder = 999;

    #[ORM\Column(type: Types::BOOLEAN)]
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

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): self
    {
        $this->shop = $shop;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getCountryCodes(): array
    {
        return $this->countryCodes;
    }

    public function setCountryCodes(array $countryCodes): self
    {
        $this->countryCodes = array_map('strtoupper', $countryCodes);

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

    public function getRate(): float
    {
        return (float) $this->rate;
    }

    public function setRate(float $rate): self
    {
        $this->rate = number_format($rate, 2, '.', '');

        return $this;
    }

    public function isPreset(): bool
    {
        return $this->isPreset;
    }

    public function setIsPreset(bool $isPreset): self
    {
        $this->isPreset = $isPreset;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

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

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
