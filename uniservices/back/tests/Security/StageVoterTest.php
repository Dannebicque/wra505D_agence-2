<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use StageBundle\Entity\Stages\StagePeriode;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\CorePermissionProvider;
use App\Security\PermissionRegistry;
use App\Security\PermissionResolver;
use StageBundle\Security\StageVoter;
use App\Security\UserEffectivePermissionService;
use PHPUnit\Framework\TestCase;
use StageBundle\Entity\Stages\StageEtudiant;
use StageBundle\Enum\EtatStageEnum;
use StageBundle\Security\StagePermissionProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class StageVoterTest extends TestCase
{
    public function testStudentReadsOnlyOwnInternship(): void
    {
        $student = new Etudiant();

        self::assertTrue($this->grants($student, StageVoter::CAN_VIEW_STAGE, $this->internship($student)));
        self::assertFalse($this->grants($student, StageVoter::CAN_VIEW_STAGE, $this->internship(new Etudiant())));
        self::assertFalse($this->grants($student, StageVoter::CAN_VIEW_STAGE, null));
    }

    public function testStudentWritesOwnRequestUntilValidatedOrGraded(): void
    {
        $student = new Etudiant();

        self::assertTrue($this->grants($student, StageVoter::CAN_EDIT_STAGE, $this->internship($student)));
        self::assertFalse($this->grants($student, StageVoter::CAN_EDIT_STAGE, $this->internship(new Etudiant())));
        self::assertFalse($this->grants(
            $student,
            StageVoter::CAN_EDIT_STAGE,
            $this->internship($student)->setEtatStage(EtatStageEnum::VALIDE),
        ));
        self::assertFalse($this->grants(
            $student,
            StageVoter::CAN_EDIT_STAGE,
            $this->internship($student)->setDateValidation(new \DateTimeImmutable()),
        ));
        self::assertFalse($this->grants(
            $student,
            StageVoter::CAN_EDIT_STAGE,
            $this->internship($student)->setEvaluationNote(20.0),
        ));
        self::assertFalse($this->grants($student, StageVoter::CAN_DELETE_STAGE, $this->internship($student)));
    }

    public function testTeacherReadsButDoesNotManageInternships(): void
    {
        $department = new StructureDepartement();
        $teacher = $this->staff(['ROLE_TEACHER'], ['intranet', 'stages'], $department);
        $tutored = $this->internship(new Etudiant(), $department)->setTuteurUniversitaire($teacher);

        // Révision des accès du client : un tuteur consulte, la gestion relève des rôles stage du département.
        self::assertTrue($this->grants($teacher, StageVoter::CAN_VIEW_STAGE, $tutored));
        self::assertFalse($this->grants($teacher, StageVoter::CAN_EDIT_STAGE, $tutored));
        self::assertFalse($this->grants($teacher, StageVoter::CAN_DELETE_STAGE, $tutored));
    }

    public function testInternshipManagerManagesInternshipsOfHisDepartmentOnly(): void
    {
        $department = new StructureDepartement();
        $manager = $this->staff(['ROLE_TEACHER', 'ROLE_STAGE_MANAGER'], ['intranet', 'stages'], $department);
        $internship = $this->internship(new Etudiant(), $department)->setEtatStage(EtatStageEnum::VALIDE);
        $elsewhere = $this->internship(new Etudiant(), new StructureDepartement());

        foreach ([StageVoter::CAN_EDIT_STAGE, StageVoter::CAN_DELETE_STAGE] as $attribute) {
            self::assertTrue($this->grants($manager, $attribute, $internship), $attribute);
            self::assertFalse($this->grants($manager, $attribute, $elsewhere), $attribute);
        }
    }

    private function grants(Etudiant|Personnel $user, string $attribute, mixed $subject): bool
    {
        $registry = new PermissionRegistry([new CorePermissionProvider(), new StagePermissionProvider()]);
        $voter = new StageVoter(new UserEffectivePermissionService(new PermissionResolver($registry)));
        /** @var UserInterface $user */
        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        return VoterInterface::ACCESS_GRANTED === $voter->vote($token, $subject, [$attribute]);
    }

    private function internship(Etudiant $student, ?StructureDepartement $department = null): StageEtudiant
    {
        $internship = (new StageEtudiant())->setEtudiant($student)->setEtatStage(EtatStageEnum::AUTORISE);
        if (null !== $department) {
            $year = (new StructureAnnee())->setPn(new StructurePn((new StructureDiplome())->setDepartement($department)));
            $internship->setStagePeriode((new StagePeriode())->setSemestreProgramme((new StructureSemestre())->setAnnee($year)));
        }

        return $internship;
    }

    /**
     * @param list<string> $permissions
     * @param list<string> $packages
     */
    private function staff(array $permissions, array $packages, ?StructureDepartement $department = null): Personnel
    {
        $assignment = (new StructureDepartementPersonnel())
            ->setPermissions($permissions)
            ->setPackages($packages)
            ->setDepartement($department)
            ->setDefaut(true);

        return (new Personnel())->addDepartementPersonnel($assignment);
    }
}
