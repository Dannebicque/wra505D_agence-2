<?php

namespace App\Tests\Service\Recherche;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Recherche\Candidat;
use App\Service\Recherche\CorrespondanceTolerante;
use App\Service\Recherche\MoteurRecherche;
use App\Service\Recherche\Source\SourceDocuments;
use App\Service\Recherche\Source\SourceRechercheInterface;
use PHPUnit\Framework\TestCase;

final class MoteurRechercheTest extends TestCase
{
    /**
     * @param list<Candidat> $candidats
     */
    private function source(array $candidats): SourceRechercheInterface
    {
        return new class ($candidats) implements SourceRechercheInterface {
            /**
             * @param list<Candidat> $candidats
             */
            public function __construct(private readonly array $candidats)
            {
            }

            public function candidats(StructureDepartement $departement, Etudiant|Personnel $utilisateur): iterable
            {
                return $this->candidats;
            }
        };
    }

    /**
     * @param list<SourceRechercheInterface> $sources
     *
     * @return list<string>
     */
    private function libelles(array $sources, string $requete): array
    {
        $moteur = new MoteurRecherche($sources, new CorrespondanceTolerante());
        $resultats = $moteur->rechercher($requete, new StructureDepartement(), new Etudiant());

        return array_map(fn (array $resultat) => $resultat['candidat']->libelle, $resultats);
    }

    private function candidat(string $libelle, string $type = 'etudiant'): Candidat
    {
        return new Candidat($type, 1, $libelle, null, $libelle);
    }

    public function testClasseLesResultatsDeToutesLesSourcesParPertinence(): void
    {
        $etudiants = $this->source([$this->candidat('Jane Doering'), $this->candidat('Paul Martin')]);
        $personnels = $this->source([$this->candidat('John Doe', 'personnel')]);

        self::assertSame(['John Doe', 'Jane Doering'], $this->libelles([$etudiants, $personnels], 'doe'));
    }

    public function testDepartageLesExAequoParOrdreAlphabetique(): void
    {
        $source = $this->source([$this->candidat('Zoé Doe'), $this->candidat('Anne Doe')]);

        self::assertSame(['Anne Doe', 'Zoé Doe'], $this->libelles([$source], 'doe'));
    }

    public function testLimiteLeNombreDeResultatsParType(): void
    {
        $candidats = array_map(fn (int $i) => $this->candidat('Doe '.$i), range(1, MoteurRecherche::RESULTATS_PAR_TYPE + 5));

        self::assertCount(MoteurRecherche::RESULTATS_PAR_TYPE, $this->libelles([$this->source($candidats)], 'doe'));
    }

    public function testIgnoreUneRequeteTropCourte(): void
    {
        self::assertSame([], $this->libelles([$this->source([$this->candidat('Jane Doe')])], 'd'));
    }

    public function testUnEtudiantNeVoitPasLesDocumentsReservesAuPersonnel(): void
    {
        self::assertNotContains('PERSONNEL', SourceDocuments::visibilitesPour(new Etudiant()));
        self::assertNotContains('ETUDIANT', SourceDocuments::visibilitesPour(new Personnel()));
    }
}
