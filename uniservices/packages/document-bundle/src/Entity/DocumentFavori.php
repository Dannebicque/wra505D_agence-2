<?php

namespace DocumentBundle\Entity;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use DocumentBundle\Repository\DocumentFavoriRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un document mis en favori par un utilisateur. Chacun a les siens : le favori n'est plus un
 * attribut du document, partagé par tous.
 */
#[ORM\Entity(repositoryClass: DocumentFavoriRepository::class)]
#[ORM\Table(name: 'document_favori')]
#[ORM\UniqueConstraint(name: 'uq_document_favori_etudiant', columns: ['document_id', 'etudiant_id'])]
#[ORM\UniqueConstraint(name: 'uq_document_favori_personnel', columns: ['document_id', 'personnel_id'])]
class DocumentFavori
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\ManyToOne(targetEntity: Etudiant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Etudiant $etudiant = null;

    #[ORM\ManyToOne(targetEntity: Personnel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Personnel $personnel = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $ajouteLe;

    public function __construct(Document $document, Etudiant|Personnel $utilisateur)
    {
        $this->document = $document;
        if ($utilisateur instanceof Etudiant) {
            $this->etudiant = $utilisateur;
        } else {
            $this->personnel = $utilisateur;
        }
        $this->ajouteLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getEtudiant(): ?Etudiant
    {
        return $this->etudiant;
    }

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function getAjouteLe(): \DateTimeImmutable
    {
        return $this->ajouteLe;
    }
}
