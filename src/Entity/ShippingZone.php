<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShippingZoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingZoneRepository::class)]
#[ORM\Table(name: 'shipping_zone', indexes: [
    new ORM\Index(name: 'idx_shipping_zone_shop', columns: ['shop_id']),
])]
class ShippingZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $countries = [];

    /**
     * @var array<int, string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $postalCodes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    /**
     * @param array<int, string> $countries
     */
    public function setCountries(array $countries): self
    {
        $normalized = array_values(array_filter(array_map(static fn (string $value) => strtoupper(trim($value)), $countries)));
        $this->countries = $normalized;

        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getPostalCodes(): ?array
    {
        return $this->postalCodes;
    }

    /**
     * @param array<int, string>|null $postalCodes
     */
    public function setPostalCodes(?array $postalCodes): self
    {
        if (null === $postalCodes) {
            $this->postalCodes = null;

            return $this;
        }

        $normalized = array_values(array_filter(array_map(static fn (string $value) => strtoupper(trim($value)), $postalCodes)));
        $this->postalCodes = [] !== $normalized ? $normalized : null;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }
}
