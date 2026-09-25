<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Search\Source\SearchSourceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Interroge chaque source, garde les éléments qui correspondent à la requête et les classe.
 */
final readonly class SearchEngine
{
    public const int MINIMUM_LENGTH = 2;
    public const int RESULTS_PER_TYPE = 10;

    /**
     * @param iterable<SearchSourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(SearchSourceInterface::TAG)]
        private readonly iterable $sources,
        private FuzzyMatcher $matcher,
    ) {
    }

    /**
     * @return list<array{candidate: Candidate, score: float}>
     */
    public function search(string $query, StructureDepartement $department, Etudiant|Personnel $user): array
    {
        if (strlen($this->matcher->normalize($query)) < self::MINIMUM_LENGTH) {
            return [];
        }
        $queryWords = $this->matcher->words($query);

        $results = [];
        foreach ($this->sources as $source) {
            $matches = [];
            foreach ($source->findCandidates($department, $user) as $candidate) {
                $score = $this->matcher->scoreWords($queryWords, $this->matcher->words($candidate->text));
                if (null !== $score) {
                    $matches[] = ['candidate' => $candidate, 'score' => $score];
                }
            }
            usort($matches, $this->compare(...));
            array_push($results, ...array_slice($matches, 0, self::RESULTS_PER_TYPE));
        }
        usort($results, $this->compare(...));

        return $results;
    }

    /**
     * @param array{candidate: Candidate, score: float} $a
     * @param array{candidate: Candidate, score: float} $b
     */
    private function compare(array $a, array $b): int
    {
        return [$b['score'], $a['candidate']->label] <=> [$a['score'], $b['candidate']->label];
    }
}
