<?php

namespace App\State\Provider\Recherche;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Recherche\ResultatRecherche;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Repository\Structure\StructureDepartementPersonnelRepository;
use App\Security\DepartmentPermissionChecker;
use App\Service\Recherche\MoteurRecherche;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Lance la recherche dans le département courant de l'utilisateur connecté.
 *
 * @implements ProviderInterface<ResultatRecherche>
 */
final class RechercheProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MoteurRecherche $moteur,
        private readonly DepartmentPermissionChecker $permissionChecker,
        private readonly StructureDepartementPersonnelRepository $departementPersonnelRepository,
    ) {
    }

    /**
     * @return list<ResultatRecherche>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $utilisateur = $this->security->getUser();
        $requete = $context['filters']['q'] ?? '';
        if (!$utilisateur instanceof Etudiant && !$utilisateur instanceof Personnel || !is_string($requete)) {
            return [];
        }

        $departement = $this->departementCourant($utilisateur);
        if (null === $departement) {
            return [];
        }

        $resultats = [];
        foreach ($this->moteur->rechercher($requete, $departement, $utilisateur) as ['candidat' => $candidat, 'score' => $score]) {
            $resultats[] = new ResultatRecherche(
                $candidat->type.'-'.$candidat->id,
                $candidat->type,
                $candidat->id,
                $candidat->libelle,
                $candidat->detail,
                $score,
                $candidat->mail,
            );
        }

        return $resultats;
    }

    private function departementCourant(Etudiant|Personnel $utilisateur): ?StructureDepartement
    {
        if ($utilisateur instanceof Etudiant) {
            return $this->permissionChecker->getStudentDepartment($utilisateur);
        }

        return $this->departementPersonnelRepository
            ->findOneBy(['personnel' => $utilisateur, 'defaut' => true])
            ?->getDepartement();
    }
}
