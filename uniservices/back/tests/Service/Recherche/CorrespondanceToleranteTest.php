<?php

namespace App\Tests\Service\Recherche;

use App\Service\Recherche\CorrespondanceTolerante;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CorrespondanceToleranteTest extends TestCase
{
    private CorrespondanceTolerante $correspondance;

    protected function setUp(): void
    {
        $this->correspondance = new CorrespondanceTolerante();
    }

    public function testNormaliseLaCasseLesAccentsEtLaPonctuation(): void
    {
        self::assertSame('developpement front end', $this->correspondance->normaliser('  Développement Front-End !'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function correspondances(): iterable
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

    #[DataProvider('correspondances')]
    public function testTrouveMalgreLesFautes(string $requete, string $texte): void
    {
        self::assertNotNull($this->correspondance->score($requete, $texte));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function absences(): iterable
    {
        yield 'mot court sans tolérance' => ['jon', 'Jane Doe'];
        yield 'trop de fautes' => ['bugdte', 'Budget prévisionnel'];
        yield 'un mot de la requête sans correspondance' => ['developpement back', 'Développement front'];
        yield 'requête vide après normalisation' => ['!!', 'Jane Doe'];
    }

    #[DataProvider('absences')]
    public function testNeTrouvePasCeQuiNeCorrespondPas(string $requete, string $texte): void
    {
        self::assertNull($this->correspondance->score($requete, $texte));
    }

    public function testClasseLExactAvantLeDebutDeMotEtAvantLaFaute(): void
    {
        $exact = $this->correspondance->score('doe', 'Jane Doe');
        $debut = $this->correspondance->score('doe', 'John Doering');
        $faute = $this->correspondance->score('jnae', 'Jane Doe');

        self::assertSame(1.0, $exact);
        self::assertGreaterThan($debut, $exact);
        self::assertGreaterThan($faute, $debut);
    }
}
