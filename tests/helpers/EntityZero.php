<?php
namespace test\orm\helpers;

class EntityZero
{
    private ?int $id = null;
    private ?string $name;
    /** @var EntityTwo */
    private $entityTwo = null;

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

    public function setEntityTwo(?EntityTwo $entityTwo): void
    {
        $this->entityTwo = $entityTwo;
    }

    public function getEntityTwo(): ?EntityTwo
    {
        return $this->entityTwo;
    }
}
