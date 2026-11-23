<?php

namespace StageBundle\Security;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Security\UserEffectivePermissionService;
use StageBundle\Entity\Stages\StageAvenant;
use StageBundle\Entity\Stages\StageEtudiant;
use StageBundle\Entity\Stages\StageSoutenance;
use StageBundle\Entity\Stages\StagePeriode;
use StageBundle\Entity\Stages\StagePeriodeInterruption;
use StageBundle\Entity\Stages\StagePeriodeSoutenance;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use StageBundle\Entity\Stages\StageEtudiant;
use StageBundle\Enum\EtatStageEnum;

/**
 * Voter pour les opérations sur les entités Stage
 * Gère les droits d'accès pour : StagePeriode et gestion des stages
 */
/** @extends Voter<string, mixed> */
class StageVoter extends Voter
{
    // Permissions StagePeriode
    public const CAN_VIEW_STAGE_PERIODE = 'CAN_VIEW_STAGE_PERIODE';
    public const CAN_EDIT_STAGE_PERIODE = 'CAN_EDIT_STAGE_PERIODE';
    public const CAN_DELETE_STAGE_PERIODE = 'CAN_DELETE_STAGE_PERIODE';

    // Permissions Stage (général)
    public const CAN_VIEW_STAGE = 'CAN_VIEW_STAGE';
    public const CAN_EDIT_STAGE = 'CAN_EDIT_STAGE';
    public const CAN_DELETE_STAGE = 'CAN_DELETE_STAGE';
    public const CAN_VALIDATE_STAGE = 'CAN_VALIDATE_STAGE';

    public const CAN_MANAGE_STAGE_PERIODE = 'CAN_MANAGE_STAGE_PERIODE';

    // Permissions sur un dossier de stage précis (sujet : StageEtudiant), voir StageAccess
    public const STAGE_ETUDIANT_VIEW = 'STAGE_ETUDIANT_VIEW';
    /** Écriture selon CAN_EDIT_STAGE ; le processeur contrôle ensuite les champs et les transitions. */
    public const STAGE_ETUDIANT_EDIT = 'STAGE_ETUDIANT_EDIT';
    public const STAGE_ETUDIANT_MANAGE = 'STAGE_ETUDIANT_MANAGE';
    /** Gestion d'une période (et de ses dossiers, créneaux, interruptions) : responsable des stages de SON département. */
    public const STAGE_PERIODE_MANAGE = 'STAGE_PERIODE_MANAGE';

    private const SUPPORTED_ATTRIBUTES = [
        self::CAN_MANAGE_STAGE_PERIODE,
        self::CAN_VIEW_STAGE_PERIODE,
        self::CAN_EDIT_STAGE_PERIODE,
        self::CAN_DELETE_STAGE_PERIODE,
        self::CAN_VIEW_STAGE,
        self::CAN_EDIT_STAGE,
        self::CAN_DELETE_STAGE,
        self::CAN_VALIDATE_STAGE,
    ];

    public function __construct(
        private readonly UserEffectivePermissionService $effectivePermissionService
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    /**
     * @inheritDoc
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->allows($attribute, $subject, $token->getUser());
    }

    /** Même décision pour les opérations API et les services métier. */
    public function allows(string $attribute, mixed $subject, mixed $user): bool
    {
        $attribute = match ($attribute) {
            self::STAGE_ETUDIANT_VIEW => self::CAN_VIEW_STAGE,
            self::STAGE_ETUDIANT_EDIT => self::CAN_EDIT_STAGE,
            self::STAGE_ETUDIANT_MANAGE => self::CAN_VALIDATE_STAGE,
            self::STAGE_PERIODE_MANAGE => self::CAN_MANAGE_STAGE_PERIODE,
            default => $attribute,
        };


        // L'utilisateur doit être connecté
        if (!($user instanceof Etudiant || $user instanceof Personnel)) {
            return false;
        }

        // SUPER_ADMIN a accès à tout
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return match($attribute) {
            self::CAN_MANAGE_STAGE_PERIODE => $this->canManagePeriod($user, $subject),
            // StagePeriode
            self::CAN_VIEW_STAGE_PERIODE => $this->canViewStagePeriode($user),
            self::CAN_EDIT_STAGE_PERIODE => $this->canEditStagePeriode($user),
            self::CAN_DELETE_STAGE_PERIODE => $this->canDeleteStagePeriode($user),

            // Stage
            self::CAN_VIEW_STAGE => $this->canViewStage($subject, $user),
            self::CAN_EDIT_STAGE => $this->canEditStage($subject, $user),
            self::CAN_DELETE_STAGE => $this->canManageStage($subject, $user),
            self::CAN_VALIDATE_STAGE => $this->canManageStage($subject, $user),

            default => false,
        };
    }

    // ========== Helpers ==========

    private function isSuperAdmin(Personnel|Etudiant $user): bool
    {
        return $this->effectivePermissionService->isSuperAdmin($user);
    }

    /** @param list<string> $roles */
    private function hasAnyRole(Personnel $user, array $roles): bool
    {
        return $this->effectivePermissionService->hasAnyPermission($user, $roles);
    }

    private function canManagePeriod(Personnel|Etudiant $user, mixed $subject): bool
    {
        if (!$user instanceof Personnel) {
            return false;
        }

        $department = match (true) {
            $subject instanceof StagePeriode => $subject->getDepartement(),
            $subject instanceof StagePeriodeInterruption,
            $subject instanceof StagePeriodeSoutenance => $subject->getStagePeriode()?->getDepartement(),
            default => null,
        };

        // À la création, une période locale peut utiliser l’affectation active.
        if ($department === null && ($subject instanceof StagePeriode
                || $subject instanceof StagePeriodeInterruption
                || $subject instanceof StagePeriodeSoutenance) && $subject->getId() === null) {
            $department = $this->effectivePermissionService->getActiveDepartementPersonnel($user)?->getDepartement();
        }

        return $this->effectivePermissionService->hasAnyPermissionInDepartment($user, ['ROLE_STAGE_MANAGER'], $department);
    }

    // ========== StagePeriode ==========

    private function canViewStagePeriode(Personnel|Etudiant $user): bool
    {
        // Tout le monde peut voir les périodes de stage
        return true;
    }

    private function canEditStagePeriode(Personnel|Etudiant $user): bool
    {
        return $user instanceof Personnel && $this->hasAnyRole($user, [
            'SUPER_ADMIN',
            'ROLE_ADMIN',
            'ROLE_CHEF_DEPT',
            'ROLE_STAGE_MANAGER',
            'ROLE_SCOLARITE'
        ]);
    }

    private function canDeleteStagePeriode(Personnel|Etudiant $user): bool
    {
        return $user instanceof Personnel && $this->hasAnyRole($user, [
            'SUPER_ADMIN',
            'ROLE_ADMIN',
            'ROLE_STAGE_MANAGER'
        ]);
    }

    // ========== Stage ==========

    private function canViewStage(mixed $subject, Personnel|Etudiant $user): bool
    {
        if ($user instanceof Personnel) {
            return $this->hasAnyRole($user, [
                'SUPER_ADMIN',
                'ROLE_ADMIN',
                'ROLE_CHEF_DEPT',
                'ROLE_STAGE_MANAGER',
                'ROLE_STAGE_VIEW',
                'ROLE_SCOLARITE',
                'ROLE_PERMANENT'
            ]) || $this->isTutor($subject, $user);
        }

        // Un étudiant peut voir ses propres stages
        return $subject instanceof StageEtudiant && $subject->getEtudiant() === $user;
    }

    private function canEditStage(mixed $subject, Personnel|Etudiant $user): bool
    {
        if ($user instanceof Personnel) {
            return $this->hasAnyRole($user, [
                'SUPER_ADMIN',
                'ROLE_ADMIN',
                'ROLE_CHEF_DEPT',
                'ROLE_STAGE_MANAGER',
                'ROLE_SCOLARITE'
            ]) || $this->isTutor($subject, $user);
        }

        // Un étudiant peut modifier son propre stage, tant qu'il n'est ni validé ni évalué
        return $subject instanceof StageEtudiant
            && $subject->getEtudiant() === $user
            && in_array($subject->getEtatStage(), [EtatStageEnum::AUTORISE, EtatStageEnum::DEPOSE, EtatStageEnum::INCOMPLET], true)
            && null === $subject->getDateValidation()
            && null === $subject->getEvaluationNote();
    }

    /**
     * Le tuteur universitaire suit le stage. Seul un stage enregistré compte : un enseignant ne
     * se crée pas un stage en s'y désignant tuteur.
     */
    private function isTutor(mixed $subject, Personnel $user): bool
    {
        return $subject instanceof StageEtudiant
            && null !== $subject->getId()
            && $subject->getTuteurUniversitaire() === $user;
    }

    private function canDeleteStage(Personnel|Etudiant $user): bool
    {
        return $user instanceof Personnel && $this->hasAnyRole($user, [
            'SUPER_ADMIN',
            'ROLE_ADMIN',
            'ROLE_STAGE_MANAGER'
        ]);
    }

    private function canValidateStage(Personnel|Etudiant $user): bool
    {
        return $user instanceof Personnel && $this->hasAnyRole($user, [
            'SUPER_ADMIN',
            'ROLE_ADMIN',
            'ROLE_CHEF_DEPT',
            'ROLE_STAGE_MANAGER',
            'ROLE_DIRECTEUR_ETUDES'
        ]);
    }
}
