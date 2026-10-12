<?php

declare(strict_types=1);

namespace App\Command;

use App\Initialization\InitializationRunner;
use App\Migration\IntranetV3\ConsoleProgress;
use App\Migration\IntranetV3\DatabaseResetter;
use App\Migration\IntranetV3\MigrationContext;
use App\Migration\IntranetV3\MigrationRegistry;
use App\Migration\IntranetV3\MigrationResult;
use App\Migration\IntranetV3\MigrationRunner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Migre les données de l'intranet V3 vers uniServices, migrateur par migrateur, dans l'ordre de
 * leurs dépendances.
 */
#[AsCommand(
    name: 'app:migrate-intranet-v3',
    description: 'Migrates data from intranet V3 to uniServices.',
)]
final readonly class MigrateIntranetV3Command
{
    public function __construct(
        private MigrationRunner $runner,
        private MigrationRegistry $registry,
        private DatabaseResetter $databaseResetter,
        private InitializationRunner $initializationRunner,
        #[Autowire(param: 'kernel.environment')]
        private string $kernelEnvironment,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Argument(description: 'Migration name. Omit to run all migrations.')]
        ?string $migration = null,
        #[Option(description: 'Run without writing data.')]
        bool $dryRun = false,
        #[Option(description: 'List available migrations.')]
        bool $list = false,
        #[Option(description: 'Disable progress bars.')]
        bool $noProgress = false,
        #[Option(description: 'Empty the target application tables and stop (dev/test only).')]
        bool $resetDb = false,
        #[Option(description: 'Skip confirmation for --reset-db.', shortcut: 'f')]
        bool $force = false,
    ): int {
        if ($list) {
            $names = array_keys($this->registry->all());
            sort($names);
            $io->listing($names === [] ? ['No migration registered.'] : $names);

            return Command::SUCCESS;
        }

        if ($resetDb) {
            return $this->resetDatabase($io, $output, $dryRun, $force);
        }

        $initializationResults = [];
        if ($migration === null) {
            try {
                $initializationResults = $this->initializationRunner->run(new MigrationContext(dryRun: $dryRun, verbose: $output->isVerbose()));
            } catch (\Throwable $exception) {
                $io->error('Application initialization failed: ' . $exception->getMessage());

                return Command::FAILURE;
            }
        }

        try {
            $plan = $this->runner->plan($migration);
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $progressEnabled = !$noProgress && !$output->isQuiet();
        if ($progressEnabled && $plan !== []) {
            $io->section(sprintf('Migration intranet V3 — %d étape(s)', count($plan)));
        }

        $progress = new ConsoleProgress($output, count($plan), $progressEnabled);
        $context = $progress->context($dryRun);
        try {
            $results = $this->runner->run($migration, $context);
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } finally {
            $progress->finish();
        }

        return $this->report($io, $context, $initializationResults + $results);
    }

    private function resetDatabase(SymfonyStyle $io, OutputInterface $output, bool $dryRun, bool $force): int
    {
        if ($dryRun) {
            $io->error('--reset-db and --dry-run cannot be used together: resetting the database is destructive.');

            return Command::INVALID;
        }
        if (!in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
            $io->error(sprintf('--reset-db is restricted to dev/test environments (current environment: %s).', $this->kernelEnvironment));

            return Command::INVALID;
        }
        if (!$force && !$io->confirm(sprintf('Empty the target database application tables in %s before importing?', $this->kernelEnvironment), false)) {
            $io->warning('Database reset cancelled. No migration was run.');

            return Command::SUCCESS;
        }

        try {
            $io->section('Reset de la base cible');
            $tableCount = $this->databaseResetter->reset(static function (string $table) use ($output): void {
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  <comment>TRUNCATE</comment> %s', $table));
                }
            });
            $io->success(sprintf('Target database reset: %d application table(s) emptied.', $tableCount));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error('Unable to reset target database: ' . $exception->getMessage());
            $io->note('Si le message mentionne un lock timeout, fermez les connexions et transactions actives (application, worker, client SQL) puis relancez. -v affiche la dernière table traitée.');

            return Command::FAILURE;
        }
    }

    /**
     * @param array<string, MigrationResult> $results
     */
    private function report(SymfonyStyle $io, MigrationContext $context, array $results): int
    {
        if ($results === []) {
            $io->warning('No migration is registered yet.');

            return Command::SUCCESS;
        }

        $rows = [];
        $hasFailure = false;
        foreach ($results as $name => $result) {
            $rows[] = [$name, $result->created, $result->updated, $result->skipped, $result->failed, $result->total()];
            $hasFailure = $hasFailure || $result->failed > 0;
            if ($context->verbose) {
                foreach ($result->messages as $message) {
                    $io->writeln(sprintf('<comment>%s:</comment> %s', $name, $message));
                }
            }
        }

        $io->section('Bilan de la migration');
        $io->table(['Migration', 'Created', 'Updated', 'Skipped', 'Failed', 'Total'], $rows);
        if ($context->dryRun) {
            $io->note('Dry-run enabled: migrators must not persist changes.');
        }

        return $hasFailure ? Command::FAILURE : Command::SUCCESS;
    }
}
