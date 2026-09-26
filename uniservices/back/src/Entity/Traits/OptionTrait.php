<?php

namespace App\Entity\Traits;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait OptionTrait
{
    /** @var array<string, mixed> */
    #[ORM\Column]
    #[Groups(['diplome:read:full', 'diplome:read', 'scolarite:read', 'semestre:detail', 'annee:read', 'etudiant:read', 'questionnaire:read', 'questionnaire_section:read'])]
    private array $opt = [];

    /** @param array<string, mixed> $opt */
    public function setOpt(array $opt): static
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->opt = $resolver->resolve($opt);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getOpt(): array
    {
        return $this->opt;
    }
}
