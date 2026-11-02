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
 * Listings, trombinoscopes et exports d'un semestre : données personnelles de tous ses étudiants,
 * réservées au personnel affecté au département du semestre.
 *
 * @extends Voter<string, StructureSemestre>
 */
final class SemestreListingVoter extends Voter
{
    public const CAN_EXPORT_LISTING = 'CAN_EXPORT_LISTING';

    public function __construct(
        private readonly StructureDepartementPersonnelRepository $departementPersonnelRepository,
        private readonly UserEffectivePermissionService $permissions,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::CAN_EXPORT_LISTING && $subject instanceof StructureSemestre;
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
