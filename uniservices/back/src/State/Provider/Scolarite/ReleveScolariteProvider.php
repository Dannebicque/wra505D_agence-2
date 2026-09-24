<?php

namespace App\State\Provider\Scolarite;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Scolarite\ReleveScolarite;
use App\Entity\Users\Etudiant;
use App\Service\Scolarite\ReleveEtudiant;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Construit le relevé de l'étudiant connecté. Un personnel n'a pas de relevé.
 *
 * @implements ProviderInterface<ReleveScolarite>
 */
final class ReleveScolariteProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ReleveEtudiant $releve,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ReleveScolarite
    {
        $etudiant = $this->security->getUser();
        if (!$etudiant instanceof Etudiant) {
            throw new AccessDeniedHttpException('Le relevé de scolarité est réservé aux étudiants.');
        }

        $releve = $this->releve->pour($etudiant);

        return new ReleveScolarite($releve['anneeUniversitaire'], $releve['semestres']);
    }
}
