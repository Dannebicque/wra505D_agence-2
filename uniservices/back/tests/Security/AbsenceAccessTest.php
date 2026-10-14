<?php

declare(strict_types=1);

namespace App\Tests\Security;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Entity\Etudiant\EtudiantAbsenceJustificatif;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les absences et justificatifs de tous les étudiants étaient lisibles et modifiables sans
 * connexion : aucune opération ne portait de contrôle. Chacune doit passer par le voter du client.
 */
final class AbsenceAccessTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function resources(): iterable
    {
        yield 'absences' => [EtudiantAbsence::class];
        yield 'justificatifs' => [EtudiantAbsenceJustificatif::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('resources')]
    public function testEveryOperationGoesThroughTheVoter(string $class): void
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(ApiResource::class);
        self::assertNotEmpty($attributes);

        foreach ($attributes as $attribute) {
            foreach ($attribute->newInstance()->getOperations() ?? [] as $operation) {
                self::assertInstanceOf(Operation::class, $operation);
                self::assertMatchesRegularExpression(
                    "/is_granted\\('CAN_(VIEW|EDIT|DELETE)_(ABSENCE|JUSTIFICATIF)'/",
                    (string) $operation->getSecurity(),
                    sprintf('%s %s', $operation->getMethod(), $operation->getUriTemplate()),
                );
            }
        }
    }
}
