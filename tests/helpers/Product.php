<?php
namespace test\orm\helpers;

use DateTime;
use JetBrains\PhpStorm\Pure;
use mstodulski\database\Collection;
use mstodulski\database\LazyCollection;

class Product
{
    private ?int $id = null;
    private User $FK_Usr_createdBy;
    private DateTime $createdAt;
    private ?User $FK_Usr_updatedBy = null;
    private ?DateTime $updatedAt = null;
    protected ?int $sortOrder = null;
    private ?string $creatorBrowser = null;
    private string $name;
    private bool $archived = false;
    public ?float $weight;
    private Collection|LazyCollection $features;
    private Collection|LazyCollection $prices;
    private \test\orm\helpers\EntityOne $entityOne;
    private ?EntityTwo $entityTwo;
    /** @var ?DateTime */
    private ?DateTime $date;

    #[Pure] public function __construct()
    {
        $this->features = new Collection(Feature::class);
        $this->prices = new Collection(Price::class);
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getFeatures() : LazyCollection|Collection
    {
        return $this->features;
    }

    public function setFeatures(Collection $features): void
    {
        $this->features = $features;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatorBrowser(): ?string
    {
        return $this->creatorBrowser;
    }

    public function setCreatorBrowser(?string $creatorBrowser): void
    {
        $this->creatorBrowser = $creatorBrowser;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): void
    {
        $this->archived = $archived;
    }

    public function getFK_Usr_createdBy(): User
    {
        return $this->FK_Usr_createdBy;
    }

    public function setFK_Usr_createdBy(User $FK_Usr_createdBy): void
    {
        $this->FK_Usr_createdBy = $FK_Usr_createdBy;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getFK_Usr_updatedBy(): ?User
    {
        return $this->FK_Usr_updatedBy;
    }

    public function setFK_Usr_updatedBy(?User $FK_Usr_updatedBy): void
    {
        $this->FK_Usr_updatedBy = $FK_Usr_updatedBy;
    }

    public function getPrices(): Collection|LazyCollection
    {
        return $this->prices;
    }

    public function setPrices(Collection $prices): void
    {
        $this->prices = $prices;
    }

    public function getEntityOne(): \test\orm\helpers\EntityOne
    {
        return $this->entityOne;
    }

    public function getEntityTwo(): ?EntityTwo
    {
        return $this->entityTwo;
    }

    public function setEntityOne(EntityOne $entityOne): void
    {
        $this->entityOne = $entityOne;
    }

    public function setEntityTwo(?EntityTwo $entityTwo): void
    {
        $this->entityTwo = $entityTwo;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(?DateTime $date): void
    {
        $this->date = $date;
    }

    public function setSortOrder(?int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }
}
