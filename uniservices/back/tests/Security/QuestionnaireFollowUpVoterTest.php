<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\CorePermissionProvider;
use App\Security\PermissionRegistry;
use App\Security\PermissionResolver;
use App\Security\UserEffectivePermissionService;
use PHPUnit\Framework\TestCase;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use QuestionnaireBundle\Security\QuestionnaireFollowUpVoter;
use QuestionnaireBundle\Security\QuestionnairePermissionProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Exports, invitations et relances d'un questionnaire : personnel du département du questionnaire. */
final class QuestionnaireFollowUpVoterTest extends TestCase
{
    private StructureDepartement $departement;
    private Questionnaire $questionnaire;

    protected function setUp(): void
    {
        $this->departement = new StructureDepartement();
        $this->questionnaire = (new Questionnaire())->setDepartement($this->departement);
    }

    public function testQualityStaffOfTheDepartmentExportsAndReminds(): void
    {
        $staff = $this->staff([[$this->departement, ['ROLE_QUALITE']]]);

        self::assertTrue($this->grants($staff, QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $this->questionnaire));
        self::assertTrue($this->grants($staff, QuestionnaireFollowUpVoter::CAN_REMIND_QUESTIONNAIRE, $this->questionnaire));
    }

    public function testRoleHeldInAnotherDepartmentDoesNotCount(): void
    {
        $staff = $this->staff([
            [new StructureDepartement(), ['ROLE_QUALITE']],
            [$this->departement, ['ROLE_TEACHER']],
        ]);

        self::assertFalse($this->grants($staff, QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $this->questionnaire));
        self::assertFalse($this->grants($staff, QuestionnaireFollowUpVoter::CAN_REMIND_QUESTIONNAIRE, $this->questionnaire));
    }

    public function testStaffOutsideTheDepartmentIsDenied(): void
    {
        $staff = $this->staff([[new StructureDepartement(), ['ROLE_CHEF_DEPT']]]);

        self::assertFalse($this->grants($staff, QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $this->questionnaire));
    }

    public function testStudiesDirectorExportsButCannotRemind(): void
    {
        $staff = $this->staff([[$this->departement, ['ROLE_DIRECTEUR_ETUDES']]]);

        self::assertTrue($this->grants($staff, QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $this->questionnaire));
        self::assertFalse($this->grants($staff, QuestionnaireFollowUpVoter::CAN_REMIND_QUESTIONNAIRE, $this->questionnaire));
    }

    public function testQuestionnaireWithoutDepartmentFollowsTheActiveAssignment(): void
    {
        $global = new Questionnaire();

        self::assertTrue($this->grants($this->staff([[$this->departement, ['ROLE_QUALITE']]]), QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $global));
        self::assertFalse($this->grants($this->staff([[$this->departement, ['ROLE_TEACHER']]]), QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $global));
    }

    public function testSuperAdminFollowsEveryQuestionnaire(): void
    {
        $admin = $this->staff([[new StructureDepartement(), ['SUPER_ADMIN']]]);

        self::assertTrue($this->grants($admin, QuestionnaireFollowUpVoter::CAN_REMIND_QUESTIONNAIRE, $this->questionnaire));
    }

    public function testStudentIsAlwaysDenied(): void
    {
        self::assertFalse($this->grants(new Etudiant(), QuestionnaireFollowUpVoter::CAN_EXPORT_QUESTIONNAIRE, $this->questionnaire));
        self::assertFalse($this->grants(new Etudiant(), QuestionnaireFollowUpVoter::CAN_REMIND_QUESTIONNAIRE, $this->questionnaire));
    }

    private function grants(Etudiant|Personnel $user, string $attribute, Questionnaire $questionnaire): bool
    {
        $resolver = new PermissionResolver(new PermissionRegistry([new CorePermissionProvider(), new QuestionnairePermissionProvider()]));
        $voter = new QuestionnaireFollowUpVoter(new UserEffectivePermissionService($resolver), $resolver);
        /** @var UserInterface $user */
        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        return VoterInterface::ACCESS_GRANTED === $voter->vote($token, $questionnaire, [$attribute]);
    }

    /**
     * La première affectation est le département actif.
     *
     * @param list<array{StructureDepartement, list<string>}> $assignments
     */
    private function staff(array $assignments): Personnel
    {
        $personnel = new Personnel();
        foreach ($assignments as $index => [$departement, $permissions]) {
            $personnel->addDepartementPersonnel((new StructureDepartementPersonnel())
                ->setDepartement($departement)
                ->setPermissions($permissions)
                ->setPackages(['intranet', 'questionnaire'])
                ->setDefaut($index === 0));
        }

        return $personnel;
    }
}
