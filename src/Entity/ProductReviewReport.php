<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Enum\ReviewReportStatus;
use App\Repository\ProductReviewReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductReviewReportRepository::class)]
#[ORM\Table(name: 'product_review_report')]
#[ORM\HasLifecycleCallbacks]
class ProductReviewReport
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProductReview $review = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $reporter = null;

    #[ORM\Column(length: 255)]
    private string $reason = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(length: 20, enumType: ReviewReportStatus::class)]
    private ReviewReportStatus $status = ReviewReportStatus::Open;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReview(): ?ProductReview
    {
        return $this->review;
    }

    public function setReview(ProductReview $review): self
    {
        $this->review = $review;

        return $this;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(User $reporter): self
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getStatus(): ReviewReportStatus
    {
        return $this->status;
    }

    public function setStatus(ReviewReportStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
}
