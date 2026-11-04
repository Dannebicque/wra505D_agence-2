<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Users\Etudiant;
use PHPUnit\Framework\TestCase;

/** Vérifie la lecture des applications ouvertes à un étudiant. */
final class EtudiantPackageCodesTest extends TestCase
{
    /** Vérifie qu'un étudiant importé avant le renommage garde l'intranet. */
    public function testAncienneValeurUniTranetDonneLIntranet(): void
    {
        $etudiant = (new Etudiant())->setApplications(['UniTranet', 'Stages']);

        self::assertSame(['intranet', 'stages'], $etudiant->getPackageCodes());
    }

    /** Vérifie qu'une liste vide ou absente rend l'intranet seul. */
    public function testSansApplicationDonneLIntranet(): void
    {
        self::assertSame(['intranet'], (new Etudiant())->getPackageCodes());
        self::assertSame(['intranet'], (new Etudiant())->setApplications([' '])->getPackageCodes());
    }
}
