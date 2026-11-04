<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Personnel;
use App\Repository\Structure\StructureDepartementPersonnelRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Listings, trombinoscopes, exports et sous-commission d'un semestre : données personnelles et notes
 * de tous ses étudiants, réservées au personnel affecté au département du semestre.
 *
 * @extends Voter<string, StructureSemestre>
 */
final class SemestreListingVoter extends Voter
{
    public const CAN_EXPORT_LISTING = 'CAN_EXPORT_LISTING';
    public const CAN_VIEW_COMMISSION = 'CAN_VIEW_COMMISSION';

    public function __construct(
        private readonly StructureDepartementPersonnelRepository $departementPersonnelRepository,
        private readonly UserEffectivePermissionService $permissions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CAN_EXPORT_LISTING, self::CAN_VIEW_COMMISSION], true)
            && $subject instanceof StructureSemestre;
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

        $departement = self::departementOf($subject);
        if (!$departement instanceof StructureDepartement) {
            return false;
        }

        return $this->departementPersonnelRepository->findOneBy([
            'personnel' => $user,
            'departement' => $departement,
        ]) instanceof StructureDepartementPersonnel;
    }

    public static function departementOf(StructureSemestre $semestre): ?StructureDepartement
    {
        return $semestre->getDiplome()?->getDepartement();
    }
}
