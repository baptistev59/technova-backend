<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Entity\Traits\Timestampable;
use App\Repository\CustomerOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerOrderRepository::class)]
#[ORM\Table(name: 'customer_order', indexes: [
    new ORM\Index(name: 'idx_customer_order_status_created_at', columns: ['status', 'created_at']),
])]
#[ORM\HasLifecycleCallbacks]
class CustomerOrder
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    private ?User $owner = null;

    #[ORM\Column(length: 20, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: Types::JSON)]
    private array $shippingAddress = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $billingAddress = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $paymentSessionId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\OneToOne(mappedBy: 'order', targetEntity: Conversation::class, cascade: ['persist', 'remove'])]
    private ?Conversation $conversation = null;

    /**
     * @var Collection<int, CustomerOrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'customerOrder', targetEntity: CustomerOrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToMany(mappedBy: 'orderEntity', targetEntity: CustomerOrderStatusHistory::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $statusHistory;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderDocument::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $documents;



    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $this->documents = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return Collection<int, CustomerOrderStatusHistory>
     */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function addStatusHistory(CustomerOrderStatusHistory $history): self
    {
        if (!$this->statusHistory->contains($history)) {
            $this->statusHistory->add($history);
            $history->setOrder($this);
        }

        return $this;
    }

    public function removeStatusHistory(CustomerOrderStatusHistory $history): self
    {
        if ($this->statusHistory->removeElement($history) && $history->getOrder() === $this) {
            $history->setOrder(null);
        }

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status->value;
    }

    public function getStatusEnum(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus|string $status): self
    {
        $this->status = $status instanceof OrderStatus ? $status : OrderStatus::from($status);

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): self
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getShippingAddress(): array
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(array $shippingAddress): self
    {
        $this->shippingAddress = $shippingAddress;

        return $this;
    }

    public function getBillingAddress(): ?array
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?array $billingAddress): self
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getPaymentSessionId(): ?string
    {
        return $this->paymentSessionId;
    }

    public function setPaymentSessionId(?string $paymentSessionId): self
    {
        $this->paymentSessionId = $paymentSessionId;

        return $this;
    }

    public function getPaymentIntentId(): ?string
    {
        return $this->paymentIntentId;
    }

    public function setPaymentIntentId(?string $paymentIntentId): self
    {
        $this->paymentIntentId = $paymentIntentId;

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * @return Collection<int, CustomerOrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function addItem(CustomerOrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCustomerOrder($this);
        }

        return $this;
    }

    public function removeItem(CustomerOrderItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getCustomerOrder() === $this) {
                $item->setCustomerOrder(null);
            }
        }

        return $this;
    }

    public function involvesVendor(Vendor $vendor): bool
    {
        $vendorShopIds = array_map(
            static fn (Shop $shop): int => $shop->getId(),
            $vendor->getShops()->toArray()
        );

        foreach ($this->items as $item) {
            if ($item->getShopId() !== null && in_array($item->getShopId(), $vendorShopIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return CustomerOrderItem[]
     */
    public function getItemsForShop(Shop $shop): array
    {
        $items = [];

        foreach ($this->items as $item) {
            if ($item->getShopId() === $shop->getId()) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return Collection<int, OrderDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(OrderDocument $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setOrder($this);
        }

        return $this;
    }

    public function removeDocument(OrderDocument $document): self
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getOrder() === $this) {
                $document->setOrder(null);
            }
        }

        return $this;
    }


}
