<?php
namespace app\admin;

use DateTime;

class EntityWithoutConfiguration
{
    private ?int $id = null;
    private DateTime $createdAt;

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}