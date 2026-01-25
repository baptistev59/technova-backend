<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Repository\ProductReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductReviewRepository::class)]
#[ORM\Table(name: 'product_review')]
#[ORM\UniqueConstraint(name: 'uniq_review_author_product', columns: ['author_id', 'product_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductReview
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 2, scale: 1)]
    private string $rating = '5.0';

    #[ORM\Column(options: ['default' => true])]
    private bool $approved = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    private ?User $author = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRating(): float
    {
        return (float) $this->rating;
    }

    public function setRating(float $rating): self
    {
        $rating = max(0.0, min(5.0, $rating));
        $rating = round($rating * 2) / 2;
        $this->rating = number_format($rating, 1, '.', '');

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): self
    {
        $this->approved = $approved;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }
}
