<?php

declare(strict_types=1);

namespace App\Command\CopyBdd;

/**
 * Donne un type exact à une valeur lue dans la base de l'intranet V3, où chaque colonne arrive en mixed.
 *
 * Les commandes de copie ne déclarent pas strict_types : PHP y convertissait lui-même la valeur
 * passée à un setter typé, ou levait une TypeError. Chaque méthode reproduit cette conversion
 * à l'identique et lève une exception là où PHP levait l'erreur, pour que la copie se comporte
 * comme avant avec des types exacts.
 */
final class LegacyValue
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
        return new \UnexpectedValueException(sprintf('Expected %s from the V3 database, got %s.', $expected, get_debug_type($value)));
    }
}
