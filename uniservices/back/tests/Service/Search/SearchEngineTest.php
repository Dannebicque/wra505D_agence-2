<?php

declare(strict_types=1);

namespace App\Tests\Service\Search;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Search\Candidate;
use App\Service\Search\FuzzyMatcher;
use App\Service\Search\SearchEngine;
use App\Service\Search\Source\DocumentSearchSource;
use App\Service\Search\Source\SearchSourceInterface;
use PHPUnit\Framework\TestCase;

final class SearchEngineTest extends TestCase
{
    /**
     * @param list<Candidate> $candidates
     */
    private function source(array $candidates): SearchSourceInterface
    {
        return new class ($candidates) implements SearchSourceInterface {
            /**
             * @param list<Candidate> $candidates
             */
            public function __construct(private readonly array $candidates)
            {
            }

            public function findCandidates(StructureDepartement $department, Etudiant|Personnel $user): iterable
            {
                return $this->candidates;
            }
        };
    }

    /**
     * @param list<SearchSourceInterface> $sources
     *
     * @return list<string>
     */
    private function labels(array $sources, string $query): array
    {
        $engine = new SearchEngine($sources, new FuzzyMatcher());
        $results = $engine->search($query, new StructureDepartement(), new Etudiant());

        return array_map(fn (array $result) => $result['candidate']->label, $results);
    }

    private function candidate(string $label, string $type = 'etudiant'): Candidate
    {
        return new Candidate($type, 1, $label, null, $label);
    }

    public function testRanksResultsFromAllSourcesByRelevance(): void
    {
        $students = $this->source([$this->candidate('Jane Doering'), $this->candidate('Paul Martin')]);
        $staff = $this->source([$this->candidate('John Doe', 'personnel')]);

        self::assertSame(['John Doe', 'Jane Doering'], $this->labels([$students, $staff], 'doe'));
    }

    public function testBreaksTiesAlphabetically(): void
    {
        $source = $this->source([$this->candidate('Zoé Doe'), $this->candidate('Anne Doe')]);

        self::assertSame(['Anne Doe', 'Zoé Doe'], $this->labels([$source], 'doe'));
    }

    public function testLimitsResultsPerType(): void
    {
        $candidates = array_map(fn (int $i) => $this->candidate('Doe '.$i), range(1, SearchEngine::RESULTS_PER_TYPE + 5));

        self::assertCount(SearchEngine::RESULTS_PER_TYPE, $this->labels([$this->source($candidates)], 'doe'));
    }

    public function testIgnoresTooShortQuery(): void
    {
        self::assertSame([], $this->labels([$this->source([$this->candidate('Jane Doe')])], 'd'));
    }

    public function testStudentCannotSeeStaffOnlyDocuments(): void
    {
        self::assertNotContains('PERSONNEL', DocumentSearchSource::visibilityFor(new Etudiant()));
        self::assertNotContains('ETUDIANT', DocumentSearchSource::visibilityFor(new Personnel()));
    }
}
