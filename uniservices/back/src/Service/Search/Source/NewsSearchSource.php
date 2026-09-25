<?php

declare(strict_types=1);

namespace App\Service\Search\Source;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\TypePublicEnum;
use App\Repository\DepartementActualiteRepository;
use App\Service\Search\Candidate;

/**
 * Actualités du département destinées à l'utilisateur, selon la même règle que le tableau de bord.
 */
final readonly class NewsSearchSource implements SearchSourceInterface
{
    public function __construct(private readonly DepartementActualiteRepository $newsRepository)
    {
    }

    public function findCandidates(StructureDepartement $department, Etudiant|Personnel $user): iterable
    {
        $public = $user instanceof Etudiant
            ? TypePublicEnum::TYPE_PUBLIC_ETUDIANT
            : TypePublicEnum::TYPE_PUBLIC_PERSONNEL;

        foreach ($this->newsRepository->findByDepartementAndPublic($department, $public->value) as $news) {
            $label = $news->getLibelle();
            if (null === $label || null === $news->getId()) {
                continue;
            }

            yield new Candidate(
                'actualite',
                $news->getId(),
                $label,
                $news->getDateDebut()?->format('d/m/Y'),
                $label.' '.$news->getDescription(),
            );
        }
    }
}
