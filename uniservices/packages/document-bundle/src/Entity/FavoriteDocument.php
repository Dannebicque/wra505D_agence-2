<?php

declare(strict_types=1);

namespace DocumentBundle\Entity;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Repository\FavoriteDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un document mis en favori par un utilisateur. Chacun a les siens : le favori n'est plus un
 * attribut du document, partagé par tous.
 */
#[ORM\Entity(repositoryClass: FavoriteDocumentRepository::class)]
#[ORM\Table(name: 'document_favori')]
#[ORM\UniqueConstraint(name: 'uq_document_favori_etudiant', columns: ['document_id', 'etudiant_id'])]
#[ORM\UniqueConstraint(name: 'uq_document_favori_personnel', columns: ['document_id', 'personnel_id'])]
final class FavoriteDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\ManyToOne(targetEntity: Etudiant::class)]
    #[ORM\JoinColumn(name: 'etudiant_id', nullable: true, onDelete: 'CASCADE')]
    private ?Etudiant $student = null;

    #[ORM\ManyToOne(targetEntity: Personnel::class)]
    #[ORM\JoinColumn(name: 'personnel_id', nullable: true, onDelete: 'CASCADE')]
    private ?Personnel $staff = null;

    #[ORM\Column(name: 'ajoute_le', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $addedAt;

    public function __construct(Document $document, Etudiant|Personnel $user)
    {
        $this->document = $document;
        if ($user instanceof Etudiant) {
            $this->student = $user;
        } else {
            $this->staff = $user;
        }
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getStudent(): ?Etudiant
    {
        return $this->student;
    }

    public function getStaff(): ?Personnel
    {
        return $this->staff;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }
}
