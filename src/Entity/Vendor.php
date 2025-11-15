<?php

namespace App\Entity;

use App\Repository\VendorRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\Timestampable;

#[ORM\Entity(repositoryClass: VendorRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Vendor
{
    use Timestampable;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $businessId = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $businessIdType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\OneToOne(inversedBy: 'vendor', cascade: ['persist', 'remove'])]
    private ?Address $address = null;

    #[ORM\OneToOne(mappedBy: 'vendor', cascade: ['persist', 'remove'])]
    private ?User $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getBusinessId(): ?string
    {
        return $this->businessId;
    }

    public function setBusinessId(?string $businessId): static
    {
        $this->businessId = $businessId;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getBusinessIdType(): ?string
    {
        return $this->businessIdType;
    }

    public function setBusinessIdType(?string $businessIdType): static
    {
        $this->businessIdType = $businessIdType;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        // unset the owning side of the relation if necessary
        if ($owner === null && $this->owner !== null) {
            $this->owner->setVendor(null);
        }

        // set the owning side of the relation if necessary
        if ($owner !== null && $owner->getVendor() !== $this) {
            $owner->setVendor($this);
        }

        $this->owner = $owner;

        return $this;
    }
}
