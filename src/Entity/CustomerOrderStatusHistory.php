<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Repository\CustomerOrderStatusHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerOrderStatusHistoryRepository::class)]
#[ORM\Table(name: 'customer_order_status_history')]
#[ORM\HasLifecycleCallbacks]
class CustomerOrderStatusHistory
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CustomerOrder $orderEntity = null;

    #[ORM\Column(length: 20)]
    private string $fromStatus;

    #[ORM\Column(length: 20)]
    private string $toStatus;

    #[ORM\Column(length: 50)]
    private string $transition;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $triggeredBy = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $payload = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?CustomerOrder
    {
        return $this->orderEntity;
    }

    public function setOrder(?CustomerOrder $order): self
    {
        $this->orderEntity = $order;

        return $this;
    }

    public function getFromStatus(): string
    {
        return $this->fromStatus;
    }

    public function setFromStatus(string $fromStatus): self
    {
        $this->fromStatus = $fromStatus;

        return $this;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function setToStatus(string $toStatus): self
    {
        $this->toStatus = $toStatus;

        return $this;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function setTransition(string $transition): self
    {
        $this->transition = $transition;

        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): self
    {
        $this->changedAt = $changedAt;

        return $this;
    }

    public function getTriggeredBy(): ?string
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(?string $triggeredBy): self
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }
}
