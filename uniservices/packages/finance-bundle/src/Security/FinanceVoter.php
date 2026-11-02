<?php

declare(strict_types=1);

namespace FinanceBundle\Security;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Personnel;
use FinanceBundle\Entity\FinanceBonCommande;
use FinanceBundle\Entity\FinanceReception;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Demandes d'achat et réceptions : réservées au personnel du département de la demande qui détient
 * le rôle finance de l'action. Sans sujet (liste, fournisseurs), le rôle doit être tenu dans au
 * moins un département ; la liste est ensuite restreinte par FinanceDepartmentExtension.
 *
 * @extends Voter<string, FinanceBonCommande|FinanceReception|null>
 */
final class FinanceVoter extends Voter
{
    public const VIEW = 'FINANCE_VIEW';
    public const EDIT = 'FINANCE_EDIT';
    public const DELETE = 'FINANCE_DELETE';

    private const ROLES = [
        self::VIEW => FinanceAccess::ROLE_VIEW,
        self::EDIT => FinanceAccess::ROLE_REQUESTER,
        self::DELETE => FinanceAccess::ROLE_MANAGER,
    ];

    public function __construct(private readonly FinanceAccess $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return array_key_exists($attribute, self::ROLES)
            && ($subject === null || $subject instanceof FinanceBonCommande || $subject instanceof FinanceReception);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Personnel) {
            return false;
        }

        if ($this->access->isSuperAdmin($user)) {
            return true;
        }

        $departements = $this->access->departementsWith($user, self::ROLES[$attribute]);
        if ($subject === null) {
            return $departements !== [];
        }

        $commande = $subject instanceof FinanceReception ? $subject->getBonCommande() : $subject;
        $departement = $commande?->getDepartement();

        return $departement instanceof StructureDepartement && in_array($departement, $departements, true);
    }
}
