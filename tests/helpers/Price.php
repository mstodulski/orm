<?php
namespace test\orm\helpers;

use DateTime;

class Price
{
    private ?int $id = null;
    private User $FK_Usr_createdBy;
    private DateTime $createdAt;
    private ?User $FK_Usr_updatedBy = null;
    private ?DateTime $updatedAt = null;
    private string $name;
    private ?string $value;
    private Product $FK_Pro_product;

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getFK_Usr_createdBy(): User
    {
        return $this->FK_Usr_createdBy;
    }

    public function setFK_Usr_createdBy(User $FK_Usr_createdBy): void
    {
        $this->FK_Usr_createdBy = $FK_Usr_createdBy;
    }

    public function setUpdatedAt(?DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setFK_Usr_updatedBy(?User $FK_Usr_updatedBy): void
    {
        $this->FK_Usr_updatedBy = $FK_Usr_updatedBy;
    }

    public function getFK_Usr_updatedBy(): ?User
    {
        return $this->FK_Usr_updatedBy;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setFK_Pro_product(Product $FK_Pro_product): void
    {
        $this->FK_Pro_product = $FK_Pro_product;
    }
}
