<?php

declare(strict_types=1);

namespace App\Tests\Entity\Apc;

use App\Entity\Apc\ApcCompetence;
use PHPUnit\Framework\TestCase;

/**
 * Composantes et situations d'une compétence : une seule forme, quelle que soit leur source (fiche E19).
 */
final class ApcCompetenceTest extends TestCase
{
    public function testReadsV3LabelsAsOreofEntries(): void
    {
        $competence = (new ApcCompetence())
            ->setComposantesEssentielles(['En respectant les normes'])
            ->setSituationsProfessionnelles(['Conception d\'un site']);

        self::assertSame([['libelle' => 'En respectant les normes', 'code' => null, 'ordre' => null]], $competence->getComposantesEssentielles());
        self::assertSame([['libelle' => 'Conception d\'un site']], $competence->getSituationsProfessionnelles());
    }

    public function testKeepsOreofEntriesAsTheyAre(): void
    {
        $composante = ['libelle' => 'En respectant les normes', 'code' => 'CE1.01', 'ordre' => 1];
        $competence = (new ApcCompetence())
            ->setComposantesEssentielles([$composante])
            ->setSituationsProfessionnelles([['libelle' => 'Conception d\'un site']]);

        self::assertSame([$composante], $competence->getComposantesEssentielles());
        self::assertSame([['libelle' => 'Conception d\'un site']], $competence->getSituationsProfessionnelles());
    }
}
