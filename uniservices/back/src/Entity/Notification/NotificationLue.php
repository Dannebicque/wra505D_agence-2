<?php

namespace App\Entity\Notification;

use App\Entity\Users\Etudiant;
use App\Repository\Notification\NotificationLueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une notification que l'étudiant a lue. Les notifications ne sont pas stockées, elles sont
 * recalculées : seule leur lecture l'est, par leur clé.
 */
#[ORM\Entity(repositoryClass: NotificationLueRepository::class)]
#[ORM\Table(name: 'notification_lue')]
#[ORM\UniqueConstraint(name: 'uq_notification_lue', columns: ['etudiant_id', 'cle'])]
class NotificationLue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lueLe;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Etudiant::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Etudiant $etudiant,
        #[ORM\Column(length: 100)]
        private string $cle,
    ) {
        $this->lueLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtudiant(): Etudiant
    {
        return $this->etudiant;
    }

    public function getCle(): string
    {
        return $this->cle;
    }

    public function getLueLe(): \DateTimeImmutable
    {
        return $this->lueLe;
    }
}
