<?php

namespace App\Entity\Notification;

use App\Repository\Notification\SentMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Copie d'un e-mail que l'intranet a envoyé, une ligne par destinataire. C'est ce que l'étudiant
 * retrouve dans ses notifications : la vraie boîte universitaire n'est pas accessible d'ici.
 */
#[ORM\Entity(repositoryClass: SentMessageRepository::class)]
#[ORM\Table(name: 'message_envoye')]
#[ORM\Index(name: 'idx_message_envoye_destinataire', columns: ['destinataire'])]
final class SentMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'destinataire', length: 180)]
    private string $recipient;

    #[ORM\Column(name: 'sujet', length: 255)]
    private string $subject;

    #[ORM\Column(name: 'texte', type: Types::TEXT, nullable: true)]
    private ?string $text;

    #[ORM\Column(name: 'envoye_le', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    public function __construct(string $recipient, string $subject, ?string $text, ?\DateTimeImmutable $sentAt = null)
    {
        $this->recipient = mb_strtolower($recipient);
        $this->subject = $subject;
        $this->text = $text;
        $this->sentAt = $sentAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
