<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderItemFulfillmentStatus;
use App\Repository\CustomerOrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerOrderItemRepository::class)]
class CustomerOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CustomerOrder $customerOrder = null;

    #[ORM\Column]
    private int $productId;

    #[ORM\Column(length: 255)]
    private string $productName;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $lineTotal = '0.00';

    // VAT snapshot fields (applied at order time)
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $appliedVatPercent = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $appliedVatAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $appliedNetAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $appliedGrossAmount = '0.00';

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $vatCountryCode = null;

    #[ORM\Column(nullable: true)]
    private ?int $appliedVatId = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(nullable: true)]
    private ?int $shopId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productImage = null;

    #[ORM\Column(nullable: true)]
    private ?int $variantId = null;

    #[ORM\Column(length: 16, enumType: OrderItemFulfillmentStatus::class)]
    private OrderItemFulfillmentStatus $fulfillmentStatus = OrderItemFulfillmentStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fulfilledAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerOrder(): ?CustomerOrder
    {
        return $this->customerOrder;
    }

    public function setCustomerOrder(?CustomerOrder $customerOrder): self
    {
        $this->customerOrder = $customerOrder;

        return $this;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): self
    {
        $this->productName = $productName;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getLineTotal(): string
    {
        return $this->lineTotal;
    }

    public function setLineTotal(string $lineTotal): self
    {
        $this->lineTotal = $lineTotal;

        return $this;
    }

    public function getAppliedVatPercent(): string
    {
        return $this->appliedVatPercent;
    }

    public function setAppliedVatPercent(string $appliedVatPercent): self
    {
        $this->appliedVatPercent = $appliedVatPercent;

        return $this;
    }

    public function getAppliedVatAmount(): string
    {
        return $this->appliedVatAmount;
    }

    public function setAppliedVatAmount(string $appliedVatAmount): self
    {
        $this->appliedVatAmount = $appliedVatAmount;

        return $this;
    }

    public function getAppliedNetAmount(): string
    {
        return $this->appliedNetAmount;
    }

    public function setAppliedNetAmount(string $appliedNetAmount): self
    {
        $this->appliedNetAmount = $appliedNetAmount;

        return $this;
    }

    public function getAppliedGrossAmount(): string
    {
        return $this->appliedGrossAmount;
    }

    public function setAppliedGrossAmount(string $appliedGrossAmount): self
    {
        $this->appliedGrossAmount = $appliedGrossAmount;

        return $this;
    }

    public function getVatCountryCode(): ?string
    {
        return $this->vatCountryCode;
    }

    public function setVatCountryCode(?string $vatCountryCode): self
    {
        $this->vatCountryCode = null !== $vatCountryCode ? strtoupper($vatCountryCode) : null;

        return $this;
    }

    public function getAppliedVatId(): ?int
    {
        return $this->appliedVatId;
    }

    public function setAppliedVatId(?int $appliedVatId): self
    {
        $this->appliedVatId = $appliedVatId;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getShopId(): ?int
    {
        return $this->shopId;
    }

    public function setShopId(?int $shopId): self
    {
        $this->shopId = $shopId;

        return $this;
    }

    public function getProductImage(): ?string
    {
        return $this->productImage;
    }

    public function setProductImage(?string $productImage): self
    {
        $this->productImage = $productImage;

        return $this;
    }

    public function getVariantId(): ?int
    {
        return $this->variantId;
    }

    public function setVariantId(?int $variantId): self
    {
        $this->variantId = $variantId;

        return $this;
    }

    public function getFulfillmentStatus(): OrderItemFulfillmentStatus
    {
        return $this->fulfillmentStatus;
    }

    public function setFulfillmentStatus(OrderItemFulfillmentStatus $status): self
    {
        $this->fulfillmentStatus = $status;

        return $this;
    }

    public function getFulfilledAt(): ?\DateTimeImmutable
    {
        return $this->fulfilledAt;
    }

    public function setFulfilledAt(?\DateTimeImmutable $fulfilledAt): self
    {
        $this->fulfilledAt = $fulfilledAt;

        return $this;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfillmentStatus->isShipped();
    }

    public function markFulfilled(\DateTimeImmutable $date = null): self
    {
        $this->fulfillmentStatus = OrderItemFulfillmentStatus::Shipped;
        $this->fulfilledAt = $date ?? new \DateTimeImmutable();

        return $this;
    }
}
