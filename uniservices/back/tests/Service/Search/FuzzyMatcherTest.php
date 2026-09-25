<?php

declare(strict_types=1);

namespace App\Tests\Service\Search;

use App\Service\Search\FuzzyMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FuzzyMatcherTest extends TestCase
{
    private FuzzyMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new FuzzyMatcher();
    }

    public function testNormalizesCaseAccentsAndPunctuation(): void
    {
        self::assertSame('developpement front end', $this->matcher->normalize('  Développement Front-End !'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchingCases(): iterable
    {
        yield 'identique' => ['Annebicque', 'David Annebicque'];
        yield 'casse et accents' => ['developpement', 'Développement front'];
        yield 'lettre manquante' => ['anebicque', 'David Annebicque'];
        yield 'lettre en trop' => ['annebbicque', 'David Annebicque'];
        yield 'lettre remplacée' => ['budjet', 'Budget prévisionnel'];
        yield 'lettres inversées' => ['jnae', 'Jane Doe'];
        yield 'début de mot' => ['anneb', 'David Annebicque'];
        yield 'début de mot avec une faute' => ['anebi', 'David Annebicque'];
        yield 'plusieurs mots dans le désordre' => ['front developpement', 'Développement front-end'];
        yield 'deux fautes sur un mot long' => ['devlopement', 'Développement front'];
    }

    #[DataProvider('matchingCases')]
    public function testMatchesDespiteTypos(string $query, string $text): void
    {
        self::assertNotNull($this->matcher->score($query, $text));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonMatchingCases(): iterable
    {
        yield 'mot court sans tolérance' => ['jon', 'Jane Doe'];
        yield 'trop de fautes' => ['bugdte', 'Budget prévisionnel'];
        yield 'un mot de la requête sans correspondance' => ['developpement back', 'Développement front'];
        yield 'requête vide après normalisation' => ['!!', 'Jane Doe'];
    }

    #[DataProvider('nonMatchingCases')]
    public function testDoesNotMatchUnrelatedText(string $query, string $text): void
    {
        self::assertNull($this->matcher->score($query, $text));
    }

    public function testRanksExactBeforePrefixAndTypo(): void
    {
        $exact = $this->matcher->score('doe', 'Jane Doe');
        $prefix = $this->matcher->score('doe', 'John Doering');
        $typo = $this->matcher->score('jnae', 'Jane Doe');

        self::assertSame(1.0, $exact);
        self::assertGreaterThan($prefix, $exact);
        self::assertGreaterThan($typo, $prefix);
    }
}
