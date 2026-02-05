<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[ORM\Table(name: 'country')]
class Country
{
    #[ORM\Id]
    #[ORM\Column(length: 2)]
    private string $code;

    #[ORM\Column(length: 64)]
    private string $name;

    #[ORM\Column(length: 8)]
    private string $flag;

    public function __construct(string $code, string $name, string $flag)
    {
        $this->code = strtoupper($code);
        $this->name = $name;
        $this->flag = $flag;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getFlag(): string
    {
        return $this->flag;
    }

    public function setFlag(string $flag): self
    {
        $this->flag = $flag;

        return $this;
    }
}
