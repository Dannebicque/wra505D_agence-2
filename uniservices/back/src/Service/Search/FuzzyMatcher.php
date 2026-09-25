<?php

declare(strict_types=1);

namespace App\Service\Search;

/**
 * Compare une requête à un texte en tolérant les fautes de frappe, les accents et la casse.
 *
 * Chaque mot de la requête doit retrouver un mot du texte : à l'identique, en début de mot, ou à
 * quelques fautes près. Une faute est une lettre en trop, en moins, remplacée, ou deux lettres
 * voisines inversées : « jnae » est à une faute de « jane ». Le nombre de fautes admises croît avec
 * la longueur du mot, pour qu'une requête courte ne ramène pas n'importe quoi.
 */
final class FuzzyMatcher
{
    private const float EXACT_SCORE = 1.0;
    private const float PREFIX_SCORE = 0.9;
    private const float TYPO_SCORE = 0.8;
    private const float PREFIX_WITH_TYPO_SCORE = 0.7;
    private const float TYPO_PENALTY = 0.1;

    private ?\Transliterator $transliterator = null;

    public function normalize(string $text): string
    {
        $this->transliterator ??= \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $ascii = $this->transliterator?->transliterate($text);
        if (!is_string($ascii)) {
            $ascii = mb_strtolower($text);
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    /**
     * @return list<string>
     */
    public function words(string $text): array
    {
        $normalized = $this->normalize($text);

        return '' === $normalized ? [] : explode(' ', $normalized);
    }

    /**
     * Score entre 0 et 1, ou null si un mot de la requête ne retrouve aucun mot du texte.
     */
    public function score(string $query, string $text): ?float
    {
        return $this->scoreWords($this->words($query), $this->words($text));
    }

    /**
     * Même calcul que score(), sur des mots déjà normalisés : la requête ne l'est qu'une fois pour
     * tous les éléments comparés.
     *
     * @param list<string> $queryWords
     * @param list<string> $textWords
     */
    public function scoreWords(array $queryWords, array $textWords): ?float
    {
        if ([] === $queryWords || [] === $textWords) {
            return null;
        }

        $total = 0.0;
        foreach ($queryWords as $queryWord) {
            $best = 0.0;
            foreach ($textWords as $textWord) {
                $best = max($best, $this->scoreWord($queryWord, $textWord));
            }
            if (0.0 === $best) {
                return null;
            }
            $total += $best;
        }

        return $total / count($queryWords);
    }

    private function scoreWord(string $queryWord, string $textWord): float
    {
        if ($queryWord === $textWord) {
            return self::EXACT_SCORE;
        }
        if (str_starts_with($textWord, $queryWord)) {
            return self::PREFIX_SCORE;
        }

        $allowedTypos = $this->allowedTypos($queryWord);
        if (0 === $allowedTypos) {
            return 0.0;
        }

        $typos = $this->distance($queryWord, $textWord, $allowedTypos);
        if ($typos <= $allowedTypos) {
            return self::TYPO_SCORE - ($typos - 1) * self::TYPO_PENALTY;
        }

        // Mot en cours de frappe : on compare aux débuts du mot du texte. Leur longueur varie d'autant
        // que les fautes admises, car une lettre oubliée ou ajoutée décale la suite du mot.
        $queryLength = strlen($queryWord);
        $maxPrefixLength = min(strlen($textWord) - 1, $queryLength + $allowedTypos);
        for ($prefixLength = $queryLength - $allowedTypos; $prefixLength <= $maxPrefixLength; ++$prefixLength) {
            $typos = min($typos, $this->distance($queryWord, substr($textWord, 0, $prefixLength), $allowedTypos));
        }
        if ($typos <= $allowedTypos) {
            return self::PREFIX_WITH_TYPO_SCORE - ($typos - 1) * self::TYPO_PENALTY;
        }

        return 0.0;
    }

    /**
     * Nombre de fautes entre deux mots, exact tant qu'il ne dépasse pas $allowedTypos.
     *
     * levenshtein() compte une inversion de deux lettres voisines comme deux fautes, alors que
     * c'est la faute de frappe la plus courante. Il sert de filtre rapide : une inversion vaut au
     * plus deux de ses fautes, donc au-delà du double des fautes admises, inutile d'aller plus loin.
     */
    private function distance(string $a, string $b, int $allowedTypos): int
    {
        $levenshtein = levenshtein($a, $b);
        if ($levenshtein <= 1 || $levenshtein > 2 * $allowedTypos) {
            return $levenshtein;
        }

        return $this->distanceWithTranspositions($a, $b);
    }

    /**
     * Distance de Damerau-Levenshtein restreinte.
     */
    private function distanceWithTranspositions(string $a, string $b): int
    {
        $lengthA = strlen($a);
        $lengthB = strlen($b);
        $d = [];
        for ($i = 0; $i <= $lengthA; ++$i) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $lengthB; ++$j) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $lengthA; ++$i) {
            for ($j = 1; $j <= $lengthB; ++$j) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$lengthA][$lengthB];
    }

    private function allowedTypos(string $word): int
    {
        return match (true) {
            strlen($word) <= 3 => 0,
            strlen($word) <= 7 => 1,
            default => 2,
        };
    }
}
