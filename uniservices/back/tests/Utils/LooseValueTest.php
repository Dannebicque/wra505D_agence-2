<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\Utils\LooseValue;
use PHPUnit\Framework\TestCase;

/**
 * Chaque conversion doit rendre ce que PHP rendait sans strict_types, et échouer là où il échouait.
 */
final class LooseValueTest extends TestCase
{
    /**
     * Les scalaires deviennent des chaînes comme dans un appel non strict.
     */
    public function testConvertsScalarsToString(): void
    {
        self::assertSame('abc', LooseValue::string('abc'));
        self::assertSame('12', LooseValue::string(12));
        self::assertSame('1.5', LooseValue::string(1.5));
        self::assertSame('1', LooseValue::string(true));
        self::assertSame('', LooseValue::string(false));
        self::assertNull(LooseValue::nullableString(null));
    }

    /**
     * Null passé à un paramètre string non nullable levait une TypeError.
     */
    public function testRejectsNullForString(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        LooseValue::string(null);
    }

    /**
     * Les entiers acceptent les chaînes numériques, les flottants et les booléens.
     */
    public function testConvertsNumericValuesToInt(): void
    {
        self::assertSame(42, LooseValue::int(42));
        self::assertSame(42, LooseValue::int('42'));
        self::assertSame(3, LooseValue::int(3.9));
        self::assertSame(1, LooseValue::int(true));
        self::assertNull(LooseValue::nullableInt(null));
        self::assertSame(2.0, LooseValue::float('2'));
        self::assertNull(LooseValue::nullableFloat(null));
    }

    /**
     * Un volume horaire garde son type : 5 reste un entier, 1.5 un décimal.
     */
    public function testKeepsNumberType(): void
    {
        self::assertSame(5, LooseValue::number(5));
        self::assertSame(1.5, LooseValue::number(1.5));
        self::assertSame(2, LooseValue::number('2'));
        self::assertSame(2.5, LooseValue::number('2.5'));
        $this->expectException(\UnexpectedValueException::class);
        LooseValue::number(null);
    }

    /**
     * Une méthode typée ?Classe acceptait null ou une instance, et levait une TypeError sinon.
     */
    public function testChecksNullableInstance(): void
    {
        $date = new \DateTimeImmutable();
        self::assertSame($date, LooseValue::nullableInstance($date, \DateTimeInterface::class));
        self::assertNull(LooseValue::nullableInstance(null, \DateTimeInterface::class));
        $this->expectException(\UnexpectedValueException::class);
        LooseValue::nullableInstance(['id' => 1], \DateTimeInterface::class);
    }

    /**
     * Une chaîne non numérique passée à un paramètre int levait une TypeError.
     */
    public function testRejectsNonNumericStringForInt(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        LooseValue::int('abc');
    }

    /**
     * Tout scalaire devient un booléen ; null reste refusé hors version nullable.
     */
    public function testConvertsScalarsToBool(): void
    {
        self::assertTrue(LooseValue::bool(1));
        self::assertFalse(LooseValue::bool('0'));
        self::assertTrue(LooseValue::bool('oui'));
        self::assertNull(LooseValue::nullableBool(null));
    }

    /**
     * Les conversions explicites (int) et (string) acceptent null.
     */
    public function testCastsLikeExplicitCasts(): void
    {
        self::assertSame(0, LooseValue::castInt(null));
        self::assertSame(12, LooseValue::castInt('12abc'));
        self::assertSame('', LooseValue::castString(null));
        self::assertSame('7', LooseValue::castString(7));
    }

    /**
     * (float) accepte null et les scalaires.
     */
    public function testCastsToFloat(): void
    {
        self::assertSame(0.0, LooseValue::castFloat(null));
        self::assertSame(2.5, LooseValue::castFloat('2.5'));
    }

    /**
     * Une liste JSON n'est acceptée que si chaque ligne est un tableau.
     */
    public function testAcceptsOnlyListsOfRows(): void
    {
        $rows = [['libelle' => 'A'], ['libelle' => 'B']];
        self::assertSame($rows, LooseValue::rows($rows));

        $this->expectException(\UnexpectedValueException::class);
        LooseValue::rows([['libelle' => 'A'], 'corrompu']);
    }

    /**
     * Un sous-tableau reste un tableau ; une chaîne à sa place signale un export corrompu.
     */
    public function testReadsOneRow(): void
    {
        self::assertSame(['moyenne' => 12], LooseValue::row(['moyenne' => 12]));

        $this->expectException(\UnexpectedValueException::class);
        LooseValue::row('bilan');
    }

    /**
     * Les rôles et permissions décodés restent une liste de chaînes.
     */
    public function testReadsListsOfStrings(): void
    {
        self::assertSame(['ROLE_ETUDIANT'], LooseValue::strings(['ROLE_ETUDIANT']));

        $this->expectException(\UnexpectedValueException::class);
        LooseValue::strings(['role' => 'ROLE_ETUDIANT']);
    }

    /**
     * Les clés de tableau suivent les conversions de PHP.
     */
    public function testConvertsArrayKeysLikePhp(): void
    {
        self::assertSame(5, LooseValue::key(5));
        self::assertSame('a', LooseValue::key('a'));
        self::assertSame('', LooseValue::key(null));
        self::assertSame(1, LooseValue::key(true));
        self::assertSame(2, LooseValue::key(2.7));
    }
}
