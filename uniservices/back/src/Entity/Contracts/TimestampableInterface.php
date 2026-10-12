<?php

declare(strict_types=1);

namespace App\Entity\Contracts;

/**
 * Entité dont les dates de création et de modification sont tenues par TimestampableListener.
 */
interface TimestampableInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): void;

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void;
}
