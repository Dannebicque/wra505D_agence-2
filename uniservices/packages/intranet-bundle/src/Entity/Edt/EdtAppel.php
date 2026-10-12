<?php

declare(strict_types=1);

namespace IntranetBundle\Entity\Edt;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Contracts\TimestampableInterface;
use App\Entity\Edt\EdtEvent;
use App\Entity\Traits\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use IntranetBundle\Repository\Edt\EdtAppelRepository;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Appel fait, ou non, sur un créneau de l'emploi du temps : la base du suivi du pointage.
 */
#[ORM\Entity(repositoryClass: EdtAppelRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['pointage:read']],
            security: "is_granted('IS_AUTHENTICATED_FULLY') and not is_granted('ROLE_ETUDIANT')",
        ),
    ],
)]
class EdtAppel implements TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pointage:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['pointage:read', 'edt_pointage:read'])]
    private ?bool $etat = null;

    #[ORM\OneToOne(inversedBy: 'appel', cascade: ['persist', 'remove'])]
    private ?EdtEvent $edtEvent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEtat(): ?bool
    {
        return $this->etat;
    }

    public function setEtat(bool $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getEdtEvent(): ?EdtEvent
    {
        return $this->edtEvent;
    }

    public function setEdtEvent(?EdtEvent $edtEvent): static
    {
        $this->edtEvent = $edtEvent;

        return $this;
    }
}
