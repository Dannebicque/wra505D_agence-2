<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Repository\Structure\StructureDepartementPersonnelRepository;
use App\Service\Search\Source\DocumentSearchSource;
use DocumentBundle\Entity\Document;

/**
 * Décide si l'utilisateur connecté peut lire le contenu d'un document. Mêmes règles que la
 * recherche : un document trouvé doit pouvoir s'ouvrir, un document caché ne doit pas se lire.
 */
final readonly class DocumentReadAccess
{
    public function __construct(
        private DepartmentPermissionChecker $permissionChecker,
        private StructureDepartementPersonnelRepository $departmentStaffRepository,
    ) {
    }

    /**
     * Vrai si la visibilité du document est ouverte à ce type d'utilisateur et si le document
     * est commun à tous les départements ou rattaché à celui de l'utilisateur.
     */
    public function allows(Document $document, Etudiant|Personnel $user): bool
    {
        if (!in_array($document->getVisibility(), DocumentSearchSource::visibilityFor($user), true)) {
            return false;
        }

        $department = $document->getDepartement();
        if (null === $department) {
            return true;
        }

        return $this->currentDepartment($user) === $department;
    }

    private function currentDepartment(Etudiant|Personnel $user): ?StructureDepartement
    {
        if ($user instanceof Etudiant) {
            return $this->permissionChecker->getStudentDepartment($user);
        }

        return $this->departmentStaffRepository
            ->findOneBy(['personnel' => $user, 'defaut' => true])
            ?->getDepartement();
    }
}
