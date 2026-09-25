<?php

namespace App\Entity\Notification;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\ReadNotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une notification que l'étudiant a lue. Les notifications ne sont pas stockées, elles sont
 * recalculées : seule leur lecture l'est, par leur clé.
 */
#[ORM\Entity(repositoryClass: ReadNotificationRepository::class)]
#[ORM\Table(name: 'notification_lue')]
#[ORM\UniqueConstraint(name: 'uq_notification_lue', columns: ['etudiant_id', 'cle'])]
final class ReadNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'lue_le', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $readAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Etudiant::class)]
        #[ORM\JoinColumn(name: 'etudiant_id', nullable: false, onDelete: 'CASCADE')]
        private Etudiant $student,
        #[ORM\Column(name: 'cle', length: 100)]
        private string $key,
    ) {
        $this->readAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): Etudiant
    {
        return $this->student;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getReadAt(): \DateTimeImmutable
    {
        return $this->readAt;
    }
}
