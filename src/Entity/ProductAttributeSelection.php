<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductAttributeSelectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeSelectionRepository::class)]
#[ORM\Table(name: 'product_attribute_selection')]
class ProductAttributeSelection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attributeSelections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productSelections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AttributeDefinition $attribute = null;

    /**
     * @var Collection<int, AttributeValueDefinition>
     */
    #[ORM\ManyToMany(targetEntity: AttributeValueDefinition::class, inversedBy: 'productSelections')]
    #[ORM\JoinTable(name: 'product_attribute_selection_value')]
    #[ORM\JoinColumn(name: 'selection_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'value_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $values;

    public function __construct()
    {
        $this->values = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAttribute(): ?AttributeDefinition
    {
        return $this->attribute;
    }

    public function setAttribute(?AttributeDefinition $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * @return Collection<int, AttributeValueDefinition>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(AttributeValueDefinition $value): self
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
        }

        return $this;
    }

    public function removeValue(AttributeValueDefinition $value): self
    {
        $this->values->removeElement($value);

        return $this;
    }
}
