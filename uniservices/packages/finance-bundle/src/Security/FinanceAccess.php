<?php

declare(strict_types=1);

namespace FinanceBundle\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Personnel;
use App\Security\PermissionResolver;
use App\Security\UserEffectivePermissionService;

/**
 * Départements où un personnel détient un rôle finance : les demandes d'achat d'un département,
 * montants, fournisseurs et pièces signées, ne sortent que pour son personnel habilité.
 */
final readonly class FinanceAccess
{
    public const ROLE_VIEW = 'ROLE_FINANCE_VIEW';
    public const ROLE_REQUESTER = 'ROLE_FINANCE_DEMANDEUR';
    public const ROLE_MANAGER = 'ROLE_FINANCE_MANAGER';

    public function __construct(
        private PermissionResolver $resolver,
        private UserEffectivePermissionService $permissions,
    ) {
    }

    public function isSuperAdmin(Personnel $user): bool
    {
        return $this->permissions->isSuperAdmin($user);
    }

    /**
     * Le rôle est résolu affectation par affectation : un rôle finance tenu dans un département
     * n'ouvre pas les demandes des autres.
     *
     * @return list<StructureDepartement>
     */
    public function departementsWith(Personnel $user, string $role): array
    {
        $departements = [];
        foreach ($user->getDepartementsPersonnel() as $assignment) {
            $departement = $assignment->getDepartement();
            if ($departement instanceof StructureDepartement && in_array($role, $this->resolver->resolveForDepartment($assignment), true)) {
                $departements[] = $departement;
            }
        }

        return $departements;
    }
}
