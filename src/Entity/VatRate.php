<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VatRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Shop;
use Doctrine\ORM\Mapping\Index;

#[ORM\Entity(repositoryClass: VatRateRepository::class)]
#[ORM\Table(name: 'vat_rate', indexes: [new ORM\Index(name: 'idx_vat_rate_country', columns: ['country_code'])])]
#[ORM\UniqueConstraint(name: 'uniq_vat_shop_country_code', columns: ['shop_id', 'country_code', 'code'])]
#[ORM\HasLifecycleCallbacks]
class VatRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Shop $shop = null;

    #[ORM\Column(length: 2)]
    private string $countryCode = '';

    // code to differentiate rates in same country (eg: STANDARD, REDUCED, BOOKS)
    #[ORM\Column(length: 32)]
    private string $code = 'STANDARD';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $label = null;

    // optional semantic type or free text
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $rate = '0.00';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Allow no-arg construction so form builders / EasyAdmin can instantiate the entity.
     * Prefer using setters to initialize meaningful values in application code.
     */
    public function __construct(string $countryCode = '', float $rate = 0.0, string $code = 'STANDARD')
    {
        $this->countryCode = '' !== $countryCode ? strtoupper($countryCode) : '';
        $this->code = $code;
        $this->setRate($rate);
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

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = strtoupper($countryCode);

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

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

    public function getRate(): float
    {
        return (float) $this->rate;
    }

    public function setRate(float $rate): self
    {
        $this->rate = number_format($rate, 2, '.', '');

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

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
