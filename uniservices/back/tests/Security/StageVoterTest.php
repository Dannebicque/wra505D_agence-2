<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\CorePermissionProvider;
use App\Security\PermissionRegistry;
use App\Security\PermissionResolver;
use App\Security\StageVoter;
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

    public function testTeacherReadsInternshipsButEditsOnlyTutoredOnes(): void
    {
        $teacher = $this->staff(['ROLE_TEACHER'], ['intranet', 'stages']);
        $tutored = $this->internship(new Etudiant())->setTuteurUniversitaire($teacher);

        self::assertTrue($this->grants($teacher, StageVoter::CAN_VIEW_STAGE, $this->internship(new Etudiant())));
        self::assertFalse($this->grants($teacher, StageVoter::CAN_EDIT_STAGE, $this->internship(new Etudiant())));
        self::assertFalse($this->grants($teacher, StageVoter::CAN_EDIT_STAGE, $tutored));
        $this->persist($tutored);
        self::assertTrue($this->grants($teacher, StageVoter::CAN_EDIT_STAGE, $tutored));
        self::assertFalse($this->grants($teacher, StageVoter::CAN_DELETE_STAGE, $tutored));
        self::assertFalse($this->grants($teacher, StageVoter::CAN_EDIT_STAGE_PERIODE, null));
    }

    public function testStaffOutsideStagesPackageSeesNoInternship(): void
    {
        $teacher = $this->staff(['ROLE_TEACHER'], ['intranet']);

        self::assertFalse($this->grants($teacher, StageVoter::CAN_VIEW_STAGE, $this->internship(new Etudiant())));
    }

    public function testInternshipManagerManagesInternshipsAndPeriods(): void
    {
        $manager = $this->staff(['ROLE_TEACHER', 'ROLE_STAGE_MANAGER'], ['intranet', 'stages']);
        $internship = $this->internship(new Etudiant())->setEtatStage(EtatStageEnum::VALIDE);

        foreach ([
            StageVoter::CAN_VIEW_STAGE,
            StageVoter::CAN_EDIT_STAGE,
            StageVoter::CAN_DELETE_STAGE,
            StageVoter::CAN_VALIDATE_STAGE,
            StageVoter::CAN_EDIT_STAGE_PERIODE,
            StageVoter::CAN_DELETE_STAGE_PERIODE,
        ] as $attribute) {
            self::assertTrue($this->grants($manager, $attribute, $internship), $attribute);
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

    private function internship(Etudiant $student): StageEtudiant
    {
        return (new StageEtudiant())->setEtudiant($student)->setEtatStage(EtatStageEnum::AUTORISE);
    }

    /**
     * @param list<string> $permissions
     * @param list<string> $packages
     */
    private function staff(array $permissions, array $packages): Personnel
    {
        $assignment = (new StructureDepartementPersonnel())
            ->setPermissions($permissions)
            ->setPackages($packages)
            ->setDefaut(true);

        return (new Personnel())->addDepartementPersonnel($assignment);
    }

    private function persist(StageEtudiant $internship): void
    {
        (new \ReflectionProperty(StageEtudiant::class, 'id'))->setValue($internship, 1);
    }
}
