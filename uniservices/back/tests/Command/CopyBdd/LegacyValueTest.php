<?php

declare(strict_types=1);

namespace App\Tests\Command\CopyBdd;

use App\Command\CopyBdd\LegacyValue;
use PHPUnit\Framework\TestCase;

/**
 * Chaque conversion doit rendre ce que PHP rendait sans strict_types, et échouer là où il échouait.
 */
final class LegacyValueTest extends TestCase
{
    /**
     * Les scalaires deviennent des chaînes comme dans un appel non strict.
     */
    public function testConvertsScalarsToString(): void
    {
        self::assertSame('abc', LegacyValue::string('abc'));
        self::assertSame('12', LegacyValue::string(12));
        self::assertSame('1.5', LegacyValue::string(1.5));
        self::assertSame('1', LegacyValue::string(true));
        self::assertSame('', LegacyValue::string(false));
        self::assertNull(LegacyValue::nullableString(null));
    }

    /**
     * Null passé à un paramètre string non nullable levait une TypeError.
     */
    public function testRejectsNullForString(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        LegacyValue::string(null);
    }

    /**
     * Les entiers acceptent les chaînes numériques, les flottants et les booléens.
     */
    public function testConvertsNumericValuesToInt(): void
    {
        self::assertSame(42, LegacyValue::int(42));
        self::assertSame(42, LegacyValue::int('42'));
        self::assertSame(3, LegacyValue::int(3.9));
        self::assertSame(1, LegacyValue::int(true));
        self::assertNull(LegacyValue::nullableInt(null));
        self::assertSame(2.0, LegacyValue::float('2'));
        self::assertNull(LegacyValue::nullableFloat(null));
    }

    /**
     * Une chaîne non numérique passée à un paramètre int levait une TypeError.
     */
    public function testRejectsNonNumericStringForInt(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        LegacyValue::int('abc');
    }

    /**
     * Tout scalaire devient un booléen ; null reste refusé hors version nullable.
     */
    public function testConvertsScalarsToBool(): void
    {
        self::assertTrue(LegacyValue::bool(1));
        self::assertFalse(LegacyValue::bool('0'));
        self::assertTrue(LegacyValue::bool('oui'));
        self::assertNull(LegacyValue::nullableBool(null));
    }

    /**
     * Les conversions explicites (int) et (string) acceptent null.
     */
    public function testCastsLikeExplicitCasts(): void
    {
        self::assertSame(0, LegacyValue::castInt(null));
        self::assertSame(12, LegacyValue::castInt('12abc'));
        self::assertSame('', LegacyValue::castString(null));
        self::assertSame('7', LegacyValue::castString(7));
    }

    /**
     * (float) accepte null et les scalaires.
     */
    public function testCastsToFloat(): void
    {
        self::assertSame(0.0, LegacyValue::castFloat(null));
        self::assertSame(2.5, LegacyValue::castFloat('2.5'));
    }

    /**
     * Une liste JSON n'est acceptée que si chaque ligne est un tableau.
     */
    public function testAcceptsOnlyListsOfRows(): void
    {
        $rows = [['libelle' => 'A'], ['libelle' => 'B']];
        self::assertSame($rows, LegacyValue::rows($rows));

        $this->expectException(\UnexpectedValueException::class);
        LegacyValue::rows([['libelle' => 'A'], 'corrompu']);
    }

    /**
     * Un sous-tableau reste un tableau ; une chaîne à sa place signale un export corrompu.
     */
    public function testReadsOneRow(): void
    {
        self::assertSame(['moyenne' => 12], LegacyValue::row(['moyenne' => 12]));

        $this->expectException(\UnexpectedValueException::class);
        LegacyValue::row('bilan');
    }

    /**
     * Les rôles et permissions décodés restent une liste de chaînes.
     */
    public function testReadsListsOfStrings(): void
    {
        self::assertSame(['ROLE_ETUDIANT'], LegacyValue::strings(['ROLE_ETUDIANT']));

        $this->expectException(\UnexpectedValueException::class);
        LegacyValue::strings(['role' => 'ROLE_ETUDIANT']);
    }

    /**
     * Les clés de tableau suivent les conversions de PHP.
     */
    public function testConvertsArrayKeysLikePhp(): void
    {
        self::assertSame(5, LegacyValue::key(5));
        self::assertSame('a', LegacyValue::key('a'));
        self::assertSame('', LegacyValue::key(null));
        self::assertSame(1, LegacyValue::key(true));
        self::assertSame(2, LegacyValue::key(2.7));
    }
}
