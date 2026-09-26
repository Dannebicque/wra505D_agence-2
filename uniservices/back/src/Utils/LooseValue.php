<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Donne un type exact à une valeur mixed : colonne lue dans la base de l'intranet V3, filtre
 * de requête, sous-tableau JSON.
 *
 * Le code qui l'emploie ne déclarait pas strict_types : PHP y convertissait lui-même la valeur
 * passée à un paramètre typé, ou levait une TypeError. Chaque méthode reproduit cette conversion
 * à l'identique et lève une exception là où PHP levait l'erreur, pour garder le comportement avec
 * des types exacts.
 */
final class LooseValue
{
    /**
     * Valeur passée à un paramètre string.
     */
    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        throw self::unexpected('string', $value);
    }

    /**
     * Valeur passée à un paramètre ?string.
     */
    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    /**
     * Valeur passée à un paramètre int.
     */
    public static function int(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        throw self::unexpected('int', $value);
    }

    /**
     * Valeur passée à un paramètre ?int.
     */
    public static function nullableInt(mixed $value): ?int
    {
        return null === $value ? null : self::int($value);
    }

    /**
     * Valeur passée à un paramètre float.
     */
    public static function float(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value) || is_bool($value) || (is_string($value) && is_numeric($value))) {
            return (float) $value;
        }

        throw self::unexpected('float', $value);
    }

    /**
     * Valeur passée à un paramètre ?float.
     */
    public static function nullableFloat(mixed $value): ?float
    {
        return null === $value ? null : self::float($value);
    }

    /**
     * Valeur passée à un paramètre int|float : un nombre garde son type, une chaîne numérique
     * devient le nombre qu'elle écrit.
     */
    public static function number(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        throw self::unexpected('int|float', $value);
    }

    /**
     * Résultat d'une requête Doctrine renvoyé par une méthode typée ?Classe.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public static function nullableInstance(mixed $value, string $class): ?object
    {
        if (null === $value || $value instanceof $class) {
            return $value;
        }

        throw self::unexpected($class, $value);
    }

    /**
     * Valeur passée à un paramètre bool.
     */
    public static function bool(mixed $value): bool
    {
        if (is_scalar($value)) {
            return (bool) $value;
        }

        throw self::unexpected('bool', $value);
    }

    /**
     * Valeur passée à un paramètre ?bool.
     */
    public static function nullableBool(mixed $value): ?bool
    {
        return null === $value ? null : self::bool($value);
    }

    /**
     * Équivalent de (int) $value, qui convertit aussi null en 0.
     */
    public static function castInt(mixed $value): int
    {
        if (null === $value || is_scalar($value)) {
            return (int) $value;
        }

        throw self::unexpected('int', $value);
    }

    /**
     * Équivalent de (string) $value, qui convertit aussi null en chaîne vide.
     */
    public static function castString(mixed $value): string
    {
        if (null === $value || is_scalar($value)) {
            return (string) $value;
        }

        throw self::unexpected('string', $value);
    }

    /**
     * Équivalent de (float) $value, qui convertit aussi null en 0.
     */
    public static function castFloat(mixed $value): float
    {
        if (null === $value || is_scalar($value)) {
            return (float) $value;
        }

        throw self::unexpected('float', $value);
    }

    /**
     * Sous-tableau d'une ligne JSON de la V3 (bilan, matière…). Un autre contenu signale un
     * export corrompu.
     *
     * @return array<array-key, mixed>
     */
    public static function row(mixed $value): array
    {
        if (!is_array($value)) {
            throw self::unexpected('array', $value);
        }

        return $value;
    }

    /**
     * Liste JSON de la V3 décodée en tableaux associatifs, parcourue ligne par ligne. Un autre
     * contenu signale un export corrompu : la copie ne produirait que des avertissements et des
     * lignes vides, elle s'arrête.
     *
     * @return array<array-key, array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            throw self::unexpected('array', $value);
        }
        $rows = [];
        foreach ($value as $key => $row) {
            if (!is_array($row)) {
                throw self::unexpected('array', $row);
            }
            $rows[$key] = $row;
        }

        return $rows;
    }

    /**
     * Liste de chaînes décodée d'un JSON de la V3 (rôles, permissions). Un autre contenu signale
     * une donnée corrompue.
     *
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw self::unexpected('list<string>', $value);
        }
        $strings = [];
        foreach ($value as $item) {
            $strings[] = self::string($item);
        }

        return $strings;
    }

    /**
     * Valeur employée comme clé de tableau, convertie comme PHP le fait : null en chaîne vide,
     * booléen et flottant en entier.
     */
    public static function key(mixed $value): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }
        if (null === $value) {
            return '';
        }
        if (is_bool($value) || is_float($value)) {
            return (int) $value;
        }

        throw self::unexpected('int|string', $value);
    }

    private static function unexpected(string $expected, mixed $value): \UnexpectedValueException
    {
        return new \UnexpectedValueException(sprintf('Expected %s, got %s.', $expected, get_debug_type($value)));
    }
}
