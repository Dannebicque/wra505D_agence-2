<?php

declare(strict_types=1);

namespace App\State\Provider\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Search\SearchResult;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Repository\Structure\StructureDepartementPersonnelRepository;
use App\Security\DepartmentPermissionChecker;
use App\Service\Search\SearchEngine;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Lance la recherche dans le département courant de l'utilisateur connecté.
 *
 * @implements ProviderInterface<SearchResult>
 */
final readonly class SearchProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private SearchEngine $searchEngine,
        private readonly DepartmentPermissionChecker $permissionChecker,
        private readonly StructureDepartementPersonnelRepository $departmentStaffRepository,
    ) {
    }

    /**
     * @return list<SearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        $query = $context['filters']['q'] ?? '';
        if (!$user instanceof Etudiant && !$user instanceof Personnel || !is_string($query)) {
            return [];
        }

        $department = $this->currentDepartment($user);
        if (null === $department) {
            return [];
        }

        $results = [];
        foreach ($this->searchEngine->search($query, $department, $user) as ['candidate' => $candidate, 'score' => $score]) {
            $results[] = new SearchResult(
                $candidate->type.'-'.$candidate->id,
                $candidate->type,
                $candidate->id,
                $candidate->label,
                $candidate->details,
                $score,
                $candidate->email,
            );
        }

        return $results;
    }

    private function currentDepartment(Etudiant|Personnel $user): ?StructureDepartement
    {
        if ($user instanceof Etudiant) {
            return $this->permissionChecker->getStudentDepartment($user);
        }

        return $this->departmentStaffRepository
            ->findOneBy(['personnel' => $user, 'defaut' => true])
            ?->getDepartement();
    }
}
