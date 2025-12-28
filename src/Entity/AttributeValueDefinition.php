<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AttributeValueDefinitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttributeValueDefinitionRepository::class)]
#[ORM\Table(name: 'attribute_value_definition')]
class AttributeValueDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le libellé de la valeur est requis.')]
    #[Assert\Length(min: 1, max: 120)]
    private ?string $label = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'La valeur technique est requise.')]
    #[Assert\Length(min: 1, max: 120)]
    private ?string $value = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\ManyToOne(inversedBy: 'values')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AttributeDefinition $attribute = null;

    /**
     * @var Collection<int, ProductAttributeSelection>
     */
    #[ORM\ManyToMany(mappedBy: 'values', targetEntity: ProductAttributeSelection::class)]
    private Collection $productSelections;

    public function __construct()
    {
        $this->productSelections = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position ?? 0;

        return $this;
    }

    public function getAttribute(): ?AttributeDefinition
    {
        return $this->attribute;
    }

    /**
     * @return Collection<int, ProductAttributeSelection>
     */
    public function getProductSelections(): Collection
    {
        return $this->productSelections;
    }

    public function setAttribute(?AttributeDefinition $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }
}
