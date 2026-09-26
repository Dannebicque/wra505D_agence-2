<?php

namespace App\State\Processor\Groupe;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\EtudiantScolariteSemestreRepository;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructureSemestre;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<object, object> */
class GroupeDeletefromSemestreProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EtudiantScolariteSemestreRepository $etudiantScolariteSemestreRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof StructureGroupe) {
            return $data;
        }

        $request = $context['request'] ?? null;
        if ($request) {
            $body = $request->toArray();
            $semestreId = $body['semestre'] ?? null;

            if ($semestreId) {
                // Récupération de l'entité StructureSemestre
                $semestre = $this->em->getRepository(StructureSemestre::class)->find($semestreId);

                $this->removeGroupeFromSemestre($data, $semestreId);

                if ($semestre) {
                    $data->removeSemestre($semestre);
                    $this->em->flush();
                }
            }
        }

        return $data;
    }

    private function removeGroupeFromSemestre(StructureGroupe $groupe, int $semestreId): void
    {
        $groupeId = $groupe->getId();
        if (null === $groupeId) {
            throw new \LogicException('Le groupe doit être persisté.');
        }
        // Retirer le groupe des EtudiantScolariteSemestre liés à ce groupe et ce semestre
        $etudiantScolariteSemestres = $this->etudiantScolariteSemestreRepository->findByGroupeAndSemestre($groupeId, $semestreId);
        foreach ($etudiantScolariteSemestres as $etudiantScolariteSemestre) {
            $etudiantScolariteSemestre->removeGroupe($groupe);
        }

        // Traiter récursivement les enfants qui appartiennent au même semestre
        foreach ($groupe->getEnfants() as $enfant) {
            if ($enfant->getSemestres()->exists(fn (int $k, $s) => $s->getId() === $semestreId)) {
                $this->removeGroupeFromSemestre($enfant, $semestreId);
                $semestre = $this->em->getRepository(StructureSemestre::class)->find($semestreId);
                if (null === $semestre) {
                    throw new \LogicException('Le semestre est introuvable.');
                }
                $enfant->removeSemestre($semestre);
            }
        }
    }
}
