<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExternalImageErrorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExternalImageErrorRepository::class)]
#[ORM\Table(name: 'external_image_error')]
class ExternalImageError
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2048, unique: true)]
    private string $url = '';

    #[ORM\Column(type: 'integer')]
    private int $statusCode = 0;

    #[ORM\Column(type: 'integer')]
    private int $occurrences = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $firstSeen;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSeen = null;

    public function __construct(string $url, int $statusCode)
    {
        $this->url = $url;
        $this->statusCode = $statusCode;
        $this->occurrences = 1;
        $this->firstSeen = new \DateTimeImmutable();
        $this->lastSeen = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getOccurrences(): int
    {
        return $this->occurrences;
    }

    public function increment(int $statusCode): self
    {
        $this->occurrences++;
        $this->statusCode = $statusCode;
        $this->lastSeen = new \DateTime();

        return $this;
    }

    public function getFirstSeen(): \DateTimeImmutable
    {
        return $this->firstSeen;
    }

    public function getLastSeen(): ?\DateTimeInterface
    {
        return $this->lastSeen;
    }
}
