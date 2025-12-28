<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductBundleItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductBundleItemRepository::class)]
#[ORM\Table(name: 'product_bundle_item')]
class ProductBundleItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bundleItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $bundle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $component = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isRequired = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBundle(): ?Product
    {
        return $this->bundle;
    }

    public function setBundle(?Product $bundle): self
    {
        $this->bundle = $bundle;

        return $this;
    }

    public function getComponent(): ?Product
    {
        return $this->component;
    }

    public function setComponent(?Product $component): self
    {
        $this->component = $component;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): self
    {
        $this->isRequired = $isRequired;

        return $this;
    }
}
