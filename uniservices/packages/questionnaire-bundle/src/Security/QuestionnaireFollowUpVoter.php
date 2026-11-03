<?php

declare(strict_types=1);

namespace QuestionnaireBundle\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Personnel;
use App\Security\PermissionResolver;
use App\Security\UserEffectivePermissionService;
use QuestionnaireBundle\Entity\Questionnaires\Questionnaire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Suivi d'un questionnaire : exports des résultats, invitations avec leur historique de relances,
 * envoi de relances. Réservé au personnel qui détient le rôle voulu dans le département du
 * questionnaire ; un questionnaire sans département relève du département actif du personnel.
 *
 * @extends Voter<string, Questionnaire>
 */
final class QuestionnaireFollowUpVoter extends Voter
{
    public const CAN_EXPORT_QUESTIONNAIRE = 'CAN_EXPORT_QUESTIONNAIRE';
    public const CAN_REMIND_QUESTIONNAIRE = 'CAN_REMIND_QUESTIONNAIRE';

    /**
     * Les rôles qui lisent les réponses dans QuestionnaireVoter. ROLE_RESP_PARCOURS n'y figure pas :
     * il n'est résolu que pour un diplôme, et un questionnaire n'en porte pas.
     */
    private const EXPORT_ROLES = ['ROLE_ADMIN', 'ROLE_QUALITE', 'ROLE_CHEF_DEPT', 'ROLE_DIRECTEUR_ETUDES'];

    /** Une relance renvoie l'invitation : les rôles qui publient le questionnaire. */
    private const REMIND_ROLES = ['ROLE_ADMIN', 'ROLE_QUALITE', 'ROLE_CHEF_DEPT'];

    public function __construct(
        private readonly UserEffectivePermissionService $permissions,
        private readonly PermissionResolver $resolver,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CAN_EXPORT_QUESTIONNAIRE, self::CAN_REMIND_QUESTIONNAIRE], true)
            && $subject instanceof Questionnaire;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Personnel) {
            return false;
        }

        if ($this->permissions->isSuperAdmin($user)) {
            return true;
        }

        $departement = $subject->getDepartement();
        $assignment = $departement === null
            ? $this->permissions->getActiveDepartementPersonnel($user)
            : self::assignmentIn($user, $departement);
        if ($assignment === null) {
            return false;
        }

        $roles = $attribute === self::CAN_REMIND_QUESTIONNAIRE ? self::REMIND_ROLES : self::EXPORT_ROLES;

        return array_intersect($roles, $this->resolver->resolveForDepartment($assignment)) !== [];
    }

    private static function assignmentIn(Personnel $user, StructureDepartement $departement): ?StructureDepartementPersonnel
    {
        foreach ($user->getDepartementsPersonnel() as $assignment) {
            $assigned = $assignment->getDepartement();
            if ($assigned === $departement || ($assigned?->getId() !== null && $assigned->getId() === $departement->getId())) {
                return $assignment;
            }
        }

        return null;
    }
}
