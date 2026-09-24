<?php

namespace App\Entity\Notification;

use App\Repository\Notification\MessageEnvoyeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Copie d'un e-mail que l'intranet a envoyé, une ligne par destinataire. C'est ce que l'étudiant
 * retrouve dans ses notifications : la vraie boîte universitaire n'est pas accessible d'ici.
 */
#[ORM\Entity(repositoryClass: MessageEnvoyeRepository::class)]
#[ORM\Table(name: 'message_envoye')]
#[ORM\Index(name: 'idx_message_envoye_destinataire', columns: ['destinataire'])]
class MessageEnvoye
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $destinataire;

    #[ORM\Column(length: 255)]
    private string $sujet;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $texte;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $envoyeLe;

    public function __construct(string $destinataire, string $sujet, ?string $texte, ?\DateTimeImmutable $envoyeLe = null)
    {
        $this->destinataire = mb_strtolower($destinataire);
        $this->sujet = $sujet;
        $this->texte = $texte;
        $this->envoyeLe = $envoyeLe ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestinataire(): string
    {
        return $this->destinataire;
    }

    public function getSujet(): string
    {
        return $this->sujet;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function getEnvoyeLe(): \DateTimeImmutable
    {
        return $this->envoyeLe;
    }
}
