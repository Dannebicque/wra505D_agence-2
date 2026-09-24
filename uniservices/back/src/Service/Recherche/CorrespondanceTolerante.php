<?php

namespace App\Service\Recherche;

/**
 * Compare une requête à un texte en tolérant les fautes de frappe, les accents et la casse.
 *
 * Chaque mot de la requête doit retrouver un mot du texte : à l'identique, en début de mot, ou à
 * quelques fautes près. Une faute est une lettre en trop, en moins, remplacée, ou deux lettres
 * voisines inversées : « jnae » est à une faute de « jane ». Le nombre de fautes admises croît avec
 * la longueur du mot, pour qu'une requête courte ne ramène pas n'importe quoi.
 */
final class CorrespondanceTolerante
{
    private const SCORE_EXACT = 1.0;
    private const SCORE_DEBUT = 0.9;
    private const SCORE_FAUTE = 0.8;
    private const SCORE_DEBUT_AVEC_FAUTE = 0.7;
    private const PENALITE_PAR_FAUTE = 0.1;

    private ?\Transliterator $translitterateur = null;

    public function normaliser(string $texte): string
    {
        $this->translitterateur ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $ascii = $this->translitterateur?->transliterate($texte);
        if (!is_string($ascii)) {
            $ascii = mb_strtolower($texte);
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    /**
     * @return list<string>
     */
    public function mots(string $texte): array
    {
        $normalise = $this->normaliser($texte);

        return '' === $normalise ? [] : explode(' ', $normalise);
    }

    /**
     * Score entre 0 et 1, ou null si un mot de la requête ne retrouve aucun mot du texte.
     */
    public function score(string $requete, string $texte): ?float
    {
        return $this->scoreMots($this->mots($requete), $this->mots($texte));
    }

    /**
     * Même calcul que score(), sur des mots déjà normalisés : la requête ne l'est qu'une fois pour
     * tous les éléments comparés.
     *
     * @param list<string> $motsRequete
     * @param list<string> $motsTexte
     */
    public function scoreMots(array $motsRequete, array $motsTexte): ?float
    {
        if ([] === $motsRequete || [] === $motsTexte) {
            return null;
        }

        $total = 0.0;
        foreach ($motsRequete as $motRequete) {
            $meilleur = 0.0;
            foreach ($motsTexte as $motTexte) {
                $meilleur = max($meilleur, $this->scoreMot($motRequete, $motTexte));
            }
            if (0.0 === $meilleur) {
                return null;
            }
            $total += $meilleur;
        }

        return $total / count($motsRequete);
    }

    private function scoreMot(string $motRequete, string $motTexte): float
    {
        if ($motRequete === $motTexte) {
            return self::SCORE_EXACT;
        }
        if (str_starts_with($motTexte, $motRequete)) {
            return self::SCORE_DEBUT;
        }

        $fautesAdmises = $this->fautesAdmises($motRequete);
        if (0 === $fautesAdmises) {
            return 0.0;
        }

        $fautes = $this->distance($motRequete, $motTexte, $fautesAdmises);
        if ($fautes <= $fautesAdmises) {
            return self::SCORE_FAUTE - ($fautes - 1) * self::PENALITE_PAR_FAUTE;
        }

        // Mot en cours de frappe : on compare aux débuts du mot du texte. Leur longueur varie d'autant
        // que les fautes admises, car une lettre oubliée ou ajoutée décale la suite du mot.
        $longueur = strlen($motRequete);
        $longueurMaximale = min(strlen($motTexte) - 1, $longueur + $fautesAdmises);
        for ($longueurDebut = $longueur - $fautesAdmises; $longueurDebut <= $longueurMaximale; ++$longueurDebut) {
            $fautes = min($fautes, $this->distance($motRequete, substr($motTexte, 0, $longueurDebut), $fautesAdmises));
        }
        if ($fautes <= $fautesAdmises) {
            return self::SCORE_DEBUT_AVEC_FAUTE - ($fautes - 1) * self::PENALITE_PAR_FAUTE;
        }

        return 0.0;
    }

    /**
     * Nombre de fautes entre deux mots, exact tant qu'il ne dépasse pas $fautesAdmises.
     *
     * levenshtein() compte une inversion de deux lettres voisines comme deux fautes, alors que
     * c'est la faute de frappe la plus courante. Il sert de filtre rapide : une inversion vaut au
     * plus deux de ses fautes, donc au-delà du double des fautes admises, inutile d'aller plus loin.
     */
    private function distance(string $a, string $b, int $fautesAdmises): int
    {
        $levenshtein = levenshtein($a, $b);
        if ($levenshtein <= 1 || $levenshtein > 2 * $fautesAdmises) {
            return $levenshtein;
        }

        return $this->distanceAvecInversions($a, $b);
    }

    /**
     * Distance de Damerau-Levenshtein restreinte.
     */
    private function distanceAvecInversions(string $a, string $b): int
    {
        $longueurA = strlen($a);
        $longueurB = strlen($b);
        $d = [];
        for ($i = 0; $i <= $longueurA; ++$i) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $longueurB; ++$j) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $longueurA; ++$i) {
            for ($j = 1; $j <= $longueurB; ++$j) {
                $cout = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cout);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$longueurA][$longueurB];
    }

    private function fautesAdmises(string $mot): int
    {
        return match (true) {
            strlen($mot) <= 3 => 0,
            strlen($mot) <= 7 => 1,
            default => 2,
        };
    }
}
