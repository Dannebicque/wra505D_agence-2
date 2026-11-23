<?php

namespace App\Tests\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\CorePermissionProvider;
use App\Security\PermissionRegistry;
use App\Security\PermissionResolver;
use App\Security\UserEffectivePermissionService;
use FinanceBundle\Entity\FinanceBonCommande;
use FinanceBundle\Enum\StatutCommandeEnum;
use FinanceBundle\Security\FinancePermissionProvider;
use FinanceBundle\Security\FinanceVoter;
use PHPUnit\Framework\TestCase;

/**
 * Règles d'accès de la finance après la révision du client : réservée au personnel habilité, le service
 * financier gère toutes les demandes, la suppression d'une demande d'autrui reste au gestionnaire.
 */
final class FinanceVoterTest extends TestCase
{
    public function testRefuseAUnEtudiantEtAUnAnonyme(): void
    {
        $voter = $this->voter();

        self::assertFalse($voter->allows(FinanceVoter::ACCES, null, new Etudiant()));
        self::assertFalse($voter->allows(FinanceVoter::ACCES, null, null));
    }

    public function testLaConsultationNeDonnePasLaSuppressionDUneDemandeDAutrui(): void
    {
        $department = new StructureDepartement();
        $reader = $this->staff(['ROLE_FINANCE_VIEW'], $department);
        $order = (new FinanceBonCommande())->setDepartement($department)->setStatut(StatutCommandeEnum::BROUILLON);

        self::assertTrue($this->voter()->allows(FinanceVoter::ACCES, null, $reader));
        self::assertFalse($this->voter()->allows(FinanceVoter::BC_SUPPRIMER, $order, $reader));
    }

    public function testLeServiceFinancierGereLesDemandesDeTousLesDepartements(): void
    {
        $manager = $this->staff(['ROLE_FINANCE_MANAGER'], new StructureDepartement());
        $order = (new FinanceBonCommande())->setDepartement(new StructureDepartement())->setStatut(StatutCommandeEnum::SOUMIS);

        self::assertTrue($this->voter()->allows(FinanceVoter::BC_GERER, $order, $manager));
    }

    public function testSansDemandeSeulLeSuperAdministrateurPasse(): void
    {
        $reader = $this->staff(['ROLE_FINANCE_VIEW'], new StructureDepartement());

        self::assertFalse($this->voter()->allows(FinanceVoter::BC_VOIR, null, $reader));
    }

    /** @param list<string> $permissions */
    private function staff(array $permissions, StructureDepartement $department): Personnel
    {
        $assignment = (new StructureDepartementPersonnel())
            ->setPermissions($permissions)
            ->setPackages(['intranet', 'finance'])
            ->setDepartement($department)
            ->setDefaut(true);

        return (new Personnel())->addDepartementPersonnel($assignment);
    }

    private function voter(): FinanceVoter
    {
        $registry = new PermissionRegistry([new CorePermissionProvider(), new FinancePermissionProvider()]);

        return new FinanceVoter(new UserEffectivePermissionService(new PermissionResolver($registry)));
    }
}
