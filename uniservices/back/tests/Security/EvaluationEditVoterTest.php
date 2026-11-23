<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEnseignementUe;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Structure\StructureUe;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\EtatEvaluationEnum;
use App\Security\PostVoter;
use App\Security\UserEffectivePermissionService;
use IntranetBundle\Entity\Previsionnel\Previsionnel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Vérifie qui peut initialiser ou modifier une évaluation (CAN_EDIT_EVAL). */
final class EvaluationEditVoterTest extends TestCase
{
    private StructureDepartement $departement;
    private StructureAnneeUniversitaire $annee;
    private ScolEnseignement $enseignement;

    protected function setUp(): void
    {
        $this->departement = self::withId(new StructureDepartement(), 1);
        $this->annee = self::withId(new StructureAnneeUniversitaire(), 1);

        $pn = new StructurePn((new StructureDiplome())->setDepartement($this->departement));
        $semestre = (new StructureSemestre())->setAnnee((new StructureAnnee())->setPn($pn));
        $this->enseignement = new ScolEnseignement();
        $this->enseignement->addEnseignementUe(
            new ScolEnseignementUe($this->enseignement, (new StructureUe())->setSemestre($semestre))
        );
    }

    /** Un personnel explicitement autorisé sur l'évaluation peut la modifier. */
    public function testAccordeAuPersonnelAutorise(): void
    {
        $personnel = self::withId(new Personnel(), 10);
        $evaluation = $this->evaluation(EtatEvaluationEnum::ETAT_INITIALISEE)->addPersonnelAutorise($personnel);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($personnel, $evaluation));
    }

    /** Un intervenant prévu sur l'enseignement cette année peut initialiser l'évaluation. */
    public function testAccordeALIntervenantPourInitialiser(): void
    {
        $personnel = $this->intervenant();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($personnel, $this->evaluation(EtatEvaluationEnum::ETAT_NON_INITIALISEE)));
    }

    /** Une fois l'évaluation initialisée, être intervenant ne suffit plus. */
    public function testRefuseALIntervenantApresInitialisation(): void
    {
        $personnel = $this->intervenant();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($personnel, $this->evaluation(EtatEvaluationEnum::ETAT_INITIALISEE)));
    }

    /** Le responsable des notes du département de l'évaluation peut la modifier. */
    public function testAccordeAuResponsableDesNotesDuDepartement(): void
    {
        $evaluation = $this->evaluation(EtatEvaluationEnum::ETAT_INITIALISEE);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(self::withId(new Personnel(), 20), $evaluation, $this->departement, role: true));
    }

    /** Le même rôle dans un autre département ne donne aucun droit. */
    public function testRefuseAuResponsableDesNotesDUnAutreDepartement(): void
    {
        $autre = self::withId(new StructureDepartement(), 2);
        $evaluation = $this->evaluation(EtatEvaluationEnum::ETAT_INITIALISEE);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(self::withId(new Personnel(), 20), $evaluation, $autre, role: true));
    }

    /** Un personnel du département sans rôle de gestion ne peut pas modifier. */
    public function testRefuseAuPersonnelSansRole(): void
    {
        $evaluation = $this->evaluation(EtatEvaluationEnum::ETAT_INITIALISEE);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(self::withId(new Personnel(), 30), $evaluation, $this->departement));
    }

    /** Un étudiant ne peut jamais modifier une évaluation. */
    public function testRefuseAUnEtudiant(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new Etudiant(), $this->evaluation(EtatEvaluationEnum::ETAT_NON_INITIALISEE)));
    }

    private function intervenant(): Personnel
    {
        $personnel = self::withId(new Personnel(), 40);
        $this->enseignement->addPrevisionnel(
            (new Previsionnel())->setPersonnel($personnel)->setAnneeUniversitaire($this->annee)
        );

        return $personnel;
    }

    private function evaluation(EtatEvaluationEnum $etat): ScolEvaluation
    {
        return (new ScolEvaluation())
            ->setEtat($etat)
            ->setAnneeUniversitaire($this->annee)
            ->setEnseignement($this->enseignement);
    }

    private function vote(
        UserInterface $user,
        ScolEvaluation $evaluation,
        ?StructureDepartement $departementActif = null,
        bool $role = false,
    ): int {
        $permissions = $this->createMock(UserEffectivePermissionService::class);
        $permissions->method('isSuperAdmin')->willReturn(false);
        $permissions->method('hasAnyPermission')->willReturn($role);
        $permissions->method('getActiveDepartementPersonnel')->willReturn(
            $departementActif === null ? null : (new StructureDepartementPersonnel())->setDepartement($departementActif)
        );

        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        return (new PostVoter($permissions))->vote($token, $evaluation, [PostVoter::CAN_EDIT_EVAL]);
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    private static function withId(object $entity, int $id): object
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);

        return $entity;
    }
}
