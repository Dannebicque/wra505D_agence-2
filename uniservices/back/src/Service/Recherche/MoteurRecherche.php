<?php

namespace App\Service\Recherche;

use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Service\Recherche\Source\SourceRechercheInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Interroge chaque source, garde les éléments qui correspondent à la requête et les classe.
 */
final class MoteurRecherche
{
    public const LONGUEUR_MINIMALE = 2;
    public const RESULTATS_PAR_TYPE = 10;

    /**
     * @param iterable<SourceRechercheInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(SourceRechercheInterface::TAG)]
        private readonly iterable $sources,
        private readonly CorrespondanceTolerante $correspondance,
    ) {
    }

    /**
     * @return list<array{candidat: Candidat, score: float}>
     */
    public function rechercher(string $requete, StructureDepartement $departement, Etudiant|Personnel $utilisateur): array
    {
        if (strlen($this->correspondance->normaliser($requete)) < self::LONGUEUR_MINIMALE) {
            return [];
        }
        $motsRequete = $this->correspondance->mots($requete);

        $resultats = [];
        foreach ($this->sources as $source) {
            $trouves = [];
            foreach ($source->candidats($departement, $utilisateur) as $candidat) {
                $score = $this->correspondance->scoreMots($motsRequete, $this->correspondance->mots($candidat->texte));
                if (null !== $score) {
                    $trouves[] = ['candidat' => $candidat, 'score' => $score];
                }
            }
            usort($trouves, $this->comparer(...));
            array_push($resultats, ...array_slice($trouves, 0, self::RESULTATS_PAR_TYPE));
        }
        usort($resultats, $this->comparer(...));

        return $resultats;
    }

    /**
     * @param array{candidat: Candidat, score: float} $a
     * @param array{candidat: Candidat, score: float} $b
     */
    private function comparer(array $a, array $b): int
    {
        return [$b['score'], $a['candidat']->libelle] <=> [$a['score'], $b['candidat']->libelle];
    }
}
