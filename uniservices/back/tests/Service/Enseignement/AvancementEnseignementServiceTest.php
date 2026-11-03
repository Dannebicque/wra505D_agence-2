<?php

declare(strict_types=1);

namespace App\Tests\Service\Enseignement;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Users\Personnel;
use App\Security\UserEffectivePermissionService;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\Enseignement\AvancementEnseignement;
use IntranetBundle\Filter\Enseignement\AvancementEnseignementFilter;
use IntranetBundle\Service\Enseignement\AvancementEnseignementService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Le suivi d'avancement repris du client : réservé au personnel, aux enseignants de l'intranet,
 * aux groupes de l'enseignement, et aucune saisie invalide n'atteint la base.
 */
final class AvancementEnseignementServiceTest extends TestCase
{
    private const GROUPE_ID = 7;

    public function testEveryOperationIsReservedToStaff(): void
    {
        $attributes = (new \ReflectionClass(AvancementEnseignement::class))->getAttributes(ApiResource::class);
        self::assertNotEmpty($attributes);

        foreach ($attributes as $attribute) {
            $resource = $attribute->newInstance();
            foreach ($resource->getOperations() ?? [] as $operation) {
                self::assertInstanceOf(Operation::class, $operation);
                self::assertSame(
                    "is_granted('IS_PERSONNEL')",
                    $operation->getSecurity() ?? $resource->getSecurity(),
                    sprintf('%s %s', $operation->getMethod(), $operation->getUriTemplate()),
                );
            }
        }
    }

    public function testRefusesStaffWithoutActiveAffectation(): void
    {
        $permissions = $this->createStub(UserEffectivePermissionService::class);
        $permissions->method('isTeacher')->willReturn(true);
        $permissions->method('getActiveDepartementPersonnel')->willReturn(null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->service($permissions)->contexte(new Personnel(), 1, null);
    }

    public function testRefusesAGroupOutsideTheTeaching(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->service()->enregistrer($this->contexte(), self::GROUPE_ID + 1, new Personnel(), $this->saisie());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'repère trop long' => [['repere' => str_repeat('a', 2001)]];
        yield 'version absente' => [['versionId' => null]];
        yield 'version négative' => [['versionId' => -1]];
        yield 'repère libre vide' => [['repere' => '   ']];
        yield 'état sans séquence' => [['etatSequence' => 'en_cours']];
        yield 'séquence sans état' => [['sequenceId' => 3]];
        yield 'séquence invalide' => [['sequenceId' => 0, 'etatSequence' => 'en_cours']];
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInputBeforeAnyWrite(array $override): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('wrapInTransaction');

        $this->expectException(BadRequestHttpException::class);
        $this->service(em: $em)->enregistrer($this->contexte(), self::GROUPE_ID, new Personnel(), $this->saisie($override));
    }

    private function service(?UserEffectivePermissionService $permissions = null, ?EntityManagerInterface $em = null): AvancementEnseignementService
    {
        return new AvancementEnseignementService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $permissions ?? $this->createStub(UserEffectivePermissionService::class),
            $this->createStub(AvancementEnseignementFilter::class),
        );
    }

    /**
     * @return array{0: ScolEnseignement, 1: StructureAnneeUniversitaire, 2: list<StructureGroupe>}
     */
    private function contexte(): array
    {
        $groupe = new StructureGroupe();
        (new \ReflectionProperty(StructureGroupe::class, 'id'))->setValue($groupe, self::GROUPE_ID);

        return [new ScolEnseignement(), new StructureAnneeUniversitaire(), [$groupe]];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function saisie(array $override = []): array
    {
        return [...['repere' => 'Chapitre 2', 'prochaineEtape' => '', 'versionId' => 0, 'sequenceId' => null, 'etatSequence' => null], ...$override];
    }
}
