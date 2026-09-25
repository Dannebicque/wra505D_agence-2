<?php

namespace App\Service\Notification;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Users\Etudiant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les semestres de l'année en cours de l'étudiant, auxquels se rattachent ses notes, ses
 * absences et les matières dont il suit les documents.
 */
class StudentSemesters
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<EtudiantScolariteSemestre>
     */
    public function forStudent(Etudiant $student): array
    {
        $scolarite = $this->entityManager->getRepository(EtudiantScolarite::class)
            ->findOneBy(['etudiant' => $student, 'actif' => true]);
        if (null === $scolarite) {
            return [];
        }

        return array_values(array_filter(
            $scolarite->getScolariteSemestre()->toArray(),
            fn (EtudiantScolariteSemestre $scolariteSemestre) => null !== $scolariteSemestre->getSemestre(),
        ));
    }
}
