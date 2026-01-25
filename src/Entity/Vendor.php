<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Repository\VendorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[Assert\NotBlank(message: 'Le nom de la société est requis.')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $businessId = null;

    #[ORM\Column(length: 25, nullable: true)]
    #[Assert\Regex(
        pattern: '/^$|^[0-9+().\s-]{6,25}$/',
        message: 'Le téléphone doit contenir uniquement des chiffres et caractères usuels.'
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $businessIdType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Le site web doit être une URL valide.', requireTld: true)]
    private ?string $website = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSuspended = false;

    #[ORM\OneToOne(inversedBy: 'vendor', cascade: ['persist', 'remove'])]
    private ?Address $address = null;

    #[ORM\OneToOne(mappedBy: 'vendor', cascade: ['persist', 'remove'])]
    private ?User $owner = null;

    /**
     * @var Collection<int, Shop>
     */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Shop::class)]
    private Collection $shops;

    public function __construct()
    {
        $this->shops = new ArrayCollection();
    }

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

    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function setIsSuspended(bool $isSuspended): self
    {
        $this->isSuspended = $isSuspended;

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
        if (null === $owner && null !== $this->owner) {
            $this->owner->setVendor(null);
        }

        // set the owning side of the relation if necessary
        if (null !== $owner && $owner->getVendor() !== $this) {
            $owner->setVendor($this);
        }

        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, Shop>
     */
    public function getShops(): Collection
    {
        return $this->shops;
    }

    public function addShop(Shop $shop): self
    {
        if (!$this->shops->contains($shop)) {
            $this->shops->add($shop);
            $shop->setOwner($this);
        }

        return $this;
    }

    public function removeShop(Shop $shop): self
    {
        if ($this->shops->removeElement($shop) && $shop->getOwner() === $this) {
            $shop->setOwner(null);
        }

        return $this;
    }
}
