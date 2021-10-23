<?php
namespace test\orm\helpers;

class EntityTwo
{
    private ?int $id = null;
    private ?string $name;

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
