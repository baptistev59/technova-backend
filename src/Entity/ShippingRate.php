<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShippingRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingRateRepository::class)]
#[ORM\Table(name: 'shipping_rate', indexes: [
    new ORM\Index(name: 'idx_shipping_rate_method', columns: ['method_id']),
])]
class ShippingRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ShippingMethod $method = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $minWeight = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $maxWeight = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMethod(): ?ShippingMethod
    {
        return $this->method;
    }

    public function setMethod(?ShippingMethod $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getMinWeight(): float
    {
        return (float) $this->minWeight;
    }

    public function setMinWeight(float $minWeight): self
    {
        $this->minWeight = number_format($minWeight, 3, '.', '');

        return $this;
    }

    public function getMaxWeight(): ?float
    {
        return null !== $this->maxWeight ? (float) $this->maxWeight : null;
    }

    public function setMaxWeight(?float $maxWeight): self
    {
        $this->maxWeight = null !== $maxWeight ? number_format($maxWeight, 3, '.', '') : null;

        return $this;
    }

    public function getPrice(): float
    {
        return (float) $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = number_format($price, 2, '.', '');

        return $this;
    }
}
