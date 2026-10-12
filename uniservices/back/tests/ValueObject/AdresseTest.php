<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\ValueObject\Adresse;
use PHPUnit\Framework\TestCase;

/**
 * Une adresse se lit depuis le JSON stocké, l'intranet V3 ou l'import Apogée, sans planter sur une
 * clé absente.
 */
final class AdresseTest extends TestCase
{
    /**
     * Le format stocké en base se relit à l'identique.
     */
    public function testReadsBackItsOwnArray(): void
    {
        $adresse = new Adresse('12 rue de la Paix', 'Bât. B', null, 'Troyes', '10000');

        self::assertEquals($adresse, Adresse::fromArray($adresse->toArray()));
        self::assertSame('', $adresse->toArray()['complement2']);
    }

    /**
     * Les clés de l'import Apogée et de l'intranet V3 sont reconnues.
     */
    public function testAcceptsLegacyKeys(): void
    {
        $adresse = Adresse::fromArray(['LIB_AD1' => ' 1 place Foch ', 'cp' => '10000', 'commune' => 'Troyes']);

        self::assertNotNull($adresse);
        self::assertSame('1 place Foch', $adresse->getAdresse());
        self::assertSame('10000', $adresse->getCodePostal());
        self::assertSame('Troyes', $adresse->getVille());
        self::assertSame('France', $adresse->getPays());
    }

    /**
     * Une adresse sans contenu n'est pas un objet vide : elle n'existe pas.
     */
    public function testEmptyDataGivesNoAddress(): void
    {
        self::assertNull(Adresse::fromArray(null));
        self::assertNull(Adresse::fromArray([]));
        self::assertNull(Adresse::fromArray(['adresse' => '  ', 'ville' => '', 'pays' => 'France']));
    }

    /**
     * Le pays n'apparaît sur une ligne que hors de France ; l'adresse complète le donne toujours.
     */
    public function testFormatsInlineAndFull(): void
    {
        $troyes = new Adresse('12 rue de la Paix', null, null, 'Troyes', '10000');
        $liege = new Adresse('1 quai Roosevelt', null, null, 'Liège', '4000', 'Belgique');

        self::assertSame('12 rue de la Paix, 10000 Troyes', (string) $troyes);
        self::assertSame('1 quai Roosevelt, 4000 Liège, Belgique', $liege->getInlineAdresse());
        self::assertSame("12 rue de la Paix\n10000 Troyes\nFrance", $troyes->getFullAdresse());
    }

    /**
     * Un pays vide retombe sur la France.
     */
    public function testBlankCountryDefaultsToFrance(): void
    {
        self::assertSame('France', (new Adresse('1 rue', pays: ' '))->getPays());
        self::assertSame('France', (new Adresse('1 rue'))->setPays(null)->getPays());
    }
}
