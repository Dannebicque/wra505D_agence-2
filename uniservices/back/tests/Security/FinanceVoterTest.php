<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\PermissionResolver;
use App\Security\UserEffectivePermissionService;
use FinanceBundle\Entity\FinanceBonCommande;
use FinanceBundle\Entity\FinanceReception;
use FinanceBundle\Security\FinanceAccess;
use FinanceBundle\Security\FinanceVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/** Vérifie que les demandes d'achat ne sortent que pour le personnel habilité de leur département. */
final class FinanceVoterTest extends TestCase
{
    private StructureDepartement $mmi;
    private StructureDepartement $gea;
    private FinanceBonCommande $commande;

    protected function setUp(): void
    {
        $this->mmi = (new StructureDepartement())->setLibelle('MMI');
        $this->gea = (new StructureDepartement())->setLibelle('GEA');
        $this->commande = (new FinanceBonCommande())->setDepartement($this->mmi);
    }

    /** Le personnel du département qui a le rôle de consultation lit la demande. */
    public function testAccordeLaLectureAuPersonnelDuDepartement(): void
    {
        $personnel = $this->personnel([[$this->mmi, ['ROLE_FINANCE_VIEW']]]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($personnel, FinanceVoter::VIEW, $this->commande));
    }

    /** Un rôle finance tenu dans un autre département n'ouvre pas la demande. */
    public function testRefuseAuPersonnelDUnAutreDepartement(): void
    {
        $personnel = $this->personnel([
            [$this->mmi, ['ROLE_TEACHER']],
            [$this->gea, ['ROLE_FINANCE_VIEW', 'ROLE_FINANCE_DEMANDEUR', 'ROLE_FINANCE_MANAGER']],
        ]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($personnel, FinanceVoter::VIEW, $this->commande));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($personnel, FinanceVoter::EDIT, $this->commande));
    }

    /** Consulter ne donne ni la modification ni la suppression. */
    public function testLaConsultationNeDonnePasLaModification(): void
    {
        $personnel = $this->personnel([[$this->mmi, ['ROLE_FINANCE_VIEW']]]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($personnel, FinanceVoter::EDIT, $this->commande));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($personnel, FinanceVoter::DELETE, $this->commande));
    }

    /** Le demandeur modifie, seul le gestionnaire supprime. */
    public function testLaSuppressionEstReserveeAuGestionnaire(): void
    {
        $demandeur = $this->personnel([[$this->mmi, ['ROLE_FINANCE_VIEW', 'ROLE_FINANCE_DEMANDEUR']]]);
        $gestionnaire = $this->personnel([[$this->mmi, ['ROLE_FINANCE_VIEW', 'ROLE_FINANCE_DEMANDEUR', 'ROLE_FINANCE_MANAGER']]]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($demandeur, FinanceVoter::EDIT, $this->commande));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($demandeur, FinanceVoter::DELETE, $this->commande));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($gestionnaire, FinanceVoter::DELETE, $this->commande));
    }

    /** Une réception suit le département de sa demande. */
    public function testUneReceptionSuitSaDemande(): void
    {
        $reception = (new FinanceReception())->setBonCommande($this->commande);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->personnel([[$this->mmi, ['ROLE_FINANCE_VIEW']]]), FinanceVoter::VIEW, $reception));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->personnel([[$this->gea, ['ROLE_FINANCE_VIEW']]]), FinanceVoter::VIEW, $reception));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->personnel([[$this->mmi, ['ROLE_FINANCE_VIEW']]]), FinanceVoter::VIEW, new FinanceReception()));
    }

    /** Une demande sans département n'est ouverte qu'au super-administrateur. */
    public function testDemandeSansDepartement(): void
    {
        $personnel = $this->personnel([[$this->mmi, ['ROLE_FINANCE_VIEW']]]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($personnel, FinanceVoter::VIEW, new FinanceBonCommande()));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(new Personnel(), FinanceVoter::VIEW, new FinanceBonCommande(), superAdmin: true));
    }

    /** Sans sujet, le rôle doit être tenu dans au moins un département. */
    public function testSansSujet(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->personnel([[$this->gea, ['ROLE_FINANCE_VIEW']]]), FinanceVoter::VIEW, null));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->personnel([[$this->mmi, ['ROLE_TEACHER']]]), FinanceVoter::VIEW, null));
    }

    /** Un étudiant et un visiteur anonyme n'ont jamais accès. */
    public function testRefuseAUnEtudiantEtAUnAnonyme(): void
    {
        $etudiant = new Etudiant();
        $token = new UsernamePasswordToken($etudiant, 'api', $etudiant->getRoles());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter(superAdmin: false)->vote($token, $this->commande, [FinanceVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter(superAdmin: false)->vote(new NullToken(), null, [FinanceVoter::VIEW]));
    }

    /**
     * @param list<array{StructureDepartement, list<string>}> $assignments département et rôles résolus
     */
    private function personnel(array $assignments): Personnel
    {
        $personnel = new Personnel();
        foreach ($assignments as [$departement, $roles]) {
            $assignment = (new StructureDepartementPersonnel())->setDepartement($departement)->setPermissions($roles);
            $personnel->addDepartementPersonnel($assignment);
        }

        return $personnel;
    }

    private function vote(Personnel $user, string $attribute, FinanceBonCommande|FinanceReception|null $subject, bool $superAdmin = false): int
    {
        return $this->voter($superAdmin)->vote($this->token($user), $subject, [$attribute]);
    }

    private function token(Personnel $user): TokenInterface
    {
        return new UsernamePasswordToken($user, 'api', $user->getRoles());
    }

    private function voter(bool $superAdmin): FinanceVoter
    {
        $resolver = $this->createMock(PermissionResolver::class);
        $resolver->method('resolveForDepartment')->willReturnCallback(
            static fn (StructureDepartementPersonnel $assignment): array => $assignment->getPermissions()
        );
        $permissions = $this->createMock(UserEffectivePermissionService::class);
        $permissions->method('isSuperAdmin')->willReturn($superAdmin);

        return new FinanceVoter(new FinanceAccess($resolver, $permissions));
    }
}
