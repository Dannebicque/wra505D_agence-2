<?php

declare(strict_types=1);

namespace StageBundle\Command;

use App\Migration\IntranetV3\ConsoleProgress;
use StageBundle\Migration\IntranetV3\InternshipDatabaseResetter;
use StageBundle\Migration\IntranetV3\InternshipIntegrityChecker;
use StageBundle\Migration\IntranetV3\InternshipMigrationRunner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Migre les périodes, entreprises, contacts et stages de l'intranet V3, après l'import du cœur
 * (app:migrate-intranet-v3).
 */
#[AsCommand(name: 'app:migrate-intranet-v3-stage', description: 'Migre les périodes et stages étudiants de l’intranet V3.')]
final readonly class MigrateIntranetV3StageCommand
{
    public function __construct(
        private InternshipMigrationRunner $runner,
        private InternshipDatabaseResetter $resetter,
        private InternshipIntegrityChecker $integrityChecker,
        #[Autowire(param: 'kernel.environment')]
        private string $kernelEnvironment,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Argument(description: 'Migration Stage à exécuter.')]
        ?string $migration = null,
        #[Option(description: 'Exécute puis annule les écritures.')]
        bool $dryRun = false,
        #[Option(description: 'Liste les migrations Stage disponibles.')]
        bool $list = false,
        #[Option(description: 'Contrôle l’intégrité de la migration Stage déjà présente en base, sans importer.')]
        bool $check = false,
        #[Option(description: 'Vide les tables du bundle Stage avant import (dev/test uniquement).')]
        bool $reset = false,
        #[Option(description: 'Ignore la confirmation de --reset.', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Désactive les barres de progression.')]
        bool $noProgress = false,
    ): int {
        if ($list) {
            $io->title('Migrations intranet V3 — Stage');
            $io->listing($this->runner->names());

            return Command::SUCCESS;
        }
        if ($check) {
            return $this->displayIntegrityReport($io, true);
        }
        if ($reset && $dryRun) {
            $io->error('--reset et --dry-run ne peuvent pas être utilisés ensemble.');

            return Command::INVALID;
        }
        if ($reset && !in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
            $io->error('--reset est réservé aux environnements dev/test.');

            return Command::FAILURE;
        }

        $prerequisites = $this->runner->checkPrerequisites();
        $io->title('Migration intranet V3 — Stage');
        $io->section('Prérequis Core');
        foreach ($prerequisites as $name => $count) {
            $io->writeln(sprintf(' %-6s %-24s %d', $count > 0 ? 'OK' : 'absent', $name, $count));
        }
        if (in_array(0, $prerequisites, true)) {
            $io->error('Import Core incomplet : exécutez d’abord app:migrate-intranet-v3.');

            return Command::FAILURE;
        }

        if ($reset) {
            if (!$force && !$io->confirm('Vider toutes les tables du bundle Stage ?', false)) {
                return Command::SUCCESS;
            }
            $io->success(sprintf('%d table(s) Stage vidée(s).', $this->resetter->reset()));
        }

        $progress = new ConsoleProgress($output, count($this->runner->names()), !$noProgress && !$output->isQuiet());
        try {
            $results = $this->runner->run($migration, $progress->context($dryRun));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            $progress->finish();
        }

        $rows = [];
        foreach ($results as $name => $result) {
            $rows[] = [$name, $result->created, $result->updated, $result->skipped, $result->failed, $result->total()];
            if ($output->isVerbose()) {
                foreach ($result->messages as $message) {
                    $io->writeln('  <comment>' . $message . '</comment>');
                }
            }
        }
        $io->newLine();
        $io->table(['Migration', 'Created', 'Updated', 'Skipped', 'Failed', 'Total'], $rows);
        if ($dryRun) {
            $io->note('Dry-run : toutes les écritures ont été annulées.');
        } else {
            $this->displayIntegrityReport($io, false);
        }

        return Command::SUCCESS;
    }

    private function displayIntegrityReport(SymfonyStyle $io, bool $withTitle): int
    {
        if ($withTitle) {
            $io->title('Contrôle d’intégrité — migration Stage');
        } else {
            $io->section('Contrôle d’intégrité');
        }
        try {
            $checks = $this->integrityChecker->check();
        } catch (\Throwable $e) {
            $io->error('Contrôle impossible : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $rows = [];
        $hasErrors = false;
        foreach ($checks as $check) {
            $rows[] = [$check['ok'] ? 'OK' : 'écart', $check['label'], $check['source'], $check['target']];
            $hasErrors = $hasErrors || !$check['ok'];
        }
        $io->table(['', 'Contrôle', 'V3', 'UniServices'], $rows);
        if ($hasErrors) {
            $io->warning('Des écarts subsistent entre V3 et UniServices.');

            return Command::FAILURE;
        }
        $io->success('Les cardinalités contrôlées sont cohérentes avec V3.');

        return Command::SUCCESS;
    }
}
