<?php

declare(strict_types=1);

namespace App\Tests\Migration\IntranetV3;

use App\Migration\IntranetV3\Contract\MigratorInterface;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationRegistry;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\MigrationRunner;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Un migrateur passe après ses dépendances, une seule fois, et un cycle est refusé.
 */
final class MigrationRunnerTest extends TestCase
{
    public function testPlansDependenciesFirstAndOnce(): void
    {
        $runner = $this->runner([new YearsMigrator(), new SemestersMigrator(), new GroupsMigrator()]);

        self::assertSame(['annees', 'semestres', 'groupes'], $runner->plan(null));
        self::assertSame(['annees', 'semestres', 'groupes'], $runner->plan('groupes'));
        self::assertSame(['annees'], $runner->plan('annees'));
    }

    public function testRunsEachMigratorOnceInDependencyOrder(): void
    {
        $log = new \ArrayObject();
        $runner = $this->runner([new GroupsMigrator($log), new SemestersMigrator($log), new YearsMigrator($log)]);

        $results = $runner->run(null, new MigrationContext());

        self::assertSame(['annees', 'semestres', 'groupes'], $log->getArrayCopy());
        self::assertSame(['annees', 'semestres', 'groupes'], array_keys($results));
    }

    public function testRefusesACircularDependency(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Circular/');

        $this->runner([new CycleAMigrator(), new CycleBMigrator()])->plan(null);
    }

    public function testRejectsAnUnknownMigration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->runner([new YearsMigrator()])->plan('inconnue');
    }

    /** @param list<MigratorInterface> $migrators */
    private function runner(array $migrators): MigrationRunner
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->createMock(Connection::class));

        return new MigrationRunner(new MigrationRegistry($migrators), $entityManager);
    }
}

/**
 * Migrateur de test qui note son passage.
 */
abstract class LoggingMigrator implements MigratorInterface
{
    /** @param \ArrayObject<int, string>|null $log */
    public function __construct(private readonly ?\ArrayObject $log = null)
    {
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function migrate(MigrationContext $context): MigrationResult
    {
        $this->log?->append($this->getName());

        return new MigrationResult(created: 1);
    }
}

final class YearsMigrator extends LoggingMigrator
{
    public function getName(): string
    {
        return 'annees';
    }
}

final class SemestersMigrator extends LoggingMigrator
{
    public function getName(): string
    {
        return 'semestres';
    }

    public function getDependencies(): array
    {
        return [YearsMigrator::class];
    }
}

final class GroupsMigrator extends LoggingMigrator
{
    public function getName(): string
    {
        return 'groupes';
    }

    public function getDependencies(): array
    {
        return [SemestersMigrator::class];
    }
}

final class CycleAMigrator extends LoggingMigrator
{
    public function getName(): string
    {
        return 'a';
    }

    public function getDependencies(): array
    {
        return [CycleBMigrator::class];
    }
}

final class CycleBMigrator extends LoggingMigrator
{
    public function getName(): string
    {
        return 'b';
    }

    public function getDependencies(): array
    {
        return [CycleAMigrator::class];
    }
}
