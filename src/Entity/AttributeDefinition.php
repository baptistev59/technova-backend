<?php

namespace App\Entity;

use App\Repository\AttributeDefinitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeDefinitionRepository::class)]
#[ORM\Table(name: 'attribute_definition')]
class AttributeDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 40, options: ['default' => 'select'])]
    private string $inputType = 'select';

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $position = 0;

    /**
     * @var Collection<int, AttributeValueDefinition>
     */
    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: AttributeValueDefinition::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $values;

    /**
     * @var Collection<int, ProductAttributeSelection>
     */
    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: ProductAttributeSelection::class)]
    private Collection $productSelections;

    public function __construct()
    {
        $this->values = new ArrayCollection();
        $this->productSelections = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function setInputType(string $inputType): self
    {
        $this->inputType = $inputType;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

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
            $value->setAttribute($this);
        }

        return $this;
    }

    public function removeValue(AttributeValueDefinition $value): self
    {
        if ($this->values->removeElement($value) && $value->getAttribute() === $this) {
            $value->setAttribute(null);
        }

        return $this;
    }
}
