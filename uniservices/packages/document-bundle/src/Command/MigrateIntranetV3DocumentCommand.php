<?php

declare(strict_types=1);

namespace DocumentBundle\Command;

use App\Migration\IntranetV3\ConsoleProgress;
use DocumentBundle\Migration\IntranetV3\DocumentDatabaseResetter;
use DocumentBundle\Migration\IntranetV3\DocumentIntegrityChecker;
use DocumentBundle\Migration\IntranetV3\DocumentFileMigrator;
use DocumentBundle\Migration\IntranetV3\DocumentMigrationRunner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Migre les catégories et documents de l’intranet V3, après l’import du cœur (app:migrate-intranet-v3).
 */
#[AsCommand(name: 'app:migrate-intranet-v3-document', description: 'Migre les catégories et documents de l’intranet V3.')]
final readonly class MigrateIntranetV3DocumentCommand
{
    public function __construct(
        private DocumentMigrationRunner $runner,
        private DocumentDatabaseResetter $resetter,
        private DocumentIntegrityChecker $integrityChecker,
        private DocumentFileMigrator $fileMigrator,
        #[Autowire(param: 'kernel.environment')]
        private string $kernelEnvironment,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Argument(description: 'Migration Document à exécuter.')]
        ?string $migration = null,
        #[Option(description: 'Exécute puis annule les écritures.')]
        bool $dryRun = false,
        #[Option(description: 'Liste les migrations Document disponibles.')]
        bool $list = false,
        #[Option(description: 'Contrôle les cardinalités V3/UniServices sans importer.')]
        bool $check = false,
        #[Option(description: 'Dossier V3 contenant public/upload/documents/.')]
        ?string $filesSource = null,
        #[Option(description: 'Dossier cible dans lequel copier/contrôler les fichiers.')]
        ?string $filesTarget = null,
        #[Option(description: 'Ne traite que les fichiers physiques (nécessite --files-source et --files-target).')]
        bool $filesOnly = false,
        #[Option(description: 'Vide les tables du bundle Document avant import (dev/test uniquement).')]
        bool $reset = false,
        #[Option(description: 'Ignore la confirmation de --reset.', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Désactive les barres de progression.')]
        bool $noProgress = false,
    ): int {
        if ($list) {
            $io->title('Migrations intranet V3 — Document');
            $io->listing($this->runner->names());

            return Command::SUCCESS;
        }
        if ($check) {
            $status = $this->displayIntegrityReport($io);
            if (null !== $filesTarget) {
                $fileStatus = $this->displayFileCheck($io, $filesTarget);
                if (Command::SUCCESS !== $fileStatus) {
                    $status = $fileStatus;
                }
            }

            return $status;
        }

        if ($filesOnly) {
            if (null === $filesSource || null === $filesTarget) {
                $io->error('--files-only nécessite --files-source et --files-target.');

                return Command::INVALID;
            }

            return $this->migrateFiles($io, $filesSource, $filesTarget, $dryRun);
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
        $missing = array_filter($prerequisites, static fn (int $count): bool => 0 === $count);
        $io->title('Migration intranet V3 — Document');
        $io->section('Prérequis Core');
        foreach ($prerequisites as $name => $count) {
            $io->writeln(sprintf(' %s %-24s %d', $count > 0 ? 'OK' : 'écart', $name, $count));
        }
        if ([] !== $missing) {
            $io->error('Import Core incomplet : les départements doivent être migrés avant les documents.');

            return Command::FAILURE;
        }

        if ($reset) {
            if (!$force && !$io->confirm('Vider les tables du bundle Document ?', false)) {
                return Command::SUCCESS;
            }
            $io->success(sprintf('%d table(s) Document vidée(s).', $this->resetter->reset()));
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
                    $io->writeln('  <comment>'.$message.'</comment>');
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

        if (null !== $filesSource || null !== $filesTarget) {
            if (null === $filesSource || null === $filesTarget) {
                $io->error('Pour migrer les fichiers, --files-source et --files-target doivent être fournis ensemble.');

                return Command::INVALID;
            }
            $fileStatus = $this->migrateFiles($io, $filesSource, $filesTarget, $dryRun);
            if (Command::SUCCESS !== $fileStatus) {
                return $fileStatus;
            }
        } else {
            $io->note('Métadonnées migrées. Pour copier les fichiers V3, fournir --files-source=<ancien public/upload/documents> et --files-target=<stockage cible>.');
        }

        return Command::SUCCESS;
    }

    private function migrateFiles(SymfonyStyle $io, string $source, string $target, bool $dryRun): int
    {
        $io->section($dryRun ? 'Simulation de migration des fichiers' : 'Migration des fichiers physiques');
        try {
            $result = $this->fileMigrator->migrate($source, $target, $dryRun);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->table(['Attendus', $dryRun ? 'Copiables' : 'Copiés', 'Déjà présents', 'Manquants V3', 'Échecs'], [[
            $result['expected'], $result['copied'], $result['existing'], $result['missing'], $result['failed'],
        ]]);
        if ([] !== $result['missingFiles']) {
            $io->warning("Fichiers absents de V3 (100 premiers) :\n".implode("\n", $result['missingFiles']));
        }
        if ([] !== $result['errors']) {
            $io->error("Erreurs de copie (100 premières) :\n".implode("\n", $result['errors']));
        }
        if ($dryRun) {
            $io->note('Dry-run fichiers : aucune copie effectuée.');
        }

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function displayFileCheck(SymfonyStyle $io, string $directory): int
    {
        $io->section('Contrôle des fichiers physiques');
        try {
            $result = $this->fileMigrator->check($directory);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $io->table(['Attendus', 'Présents', 'Manquants', 'Taille différente'], [[
            $result['expected'], $result['present'], $result['missing'], $result['wrongSize'],
        ]]);
        if ([] !== $result['missingFiles']) {
            $io->warning("Fichiers manquants (100 premiers) :\n".implode("\n", $result['missingFiles']));
        }
        if ([] !== $result['wrongSizeFiles']) {
            $io->warning("Tailles différentes (100 premières) :\n".implode("\n", $result['wrongSizeFiles']));
        }
        return ($result['missing'] > 0 || $result['wrongSize'] > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    private function displayIntegrityReport(SymfonyStyle $io, bool $withTitle = true): int
    {
        if ($withTitle) {
            $io->title('Contrôle d’intégrité — migration Document');
        } else {
            $io->section('Contrôle d’intégrité');
        }
        try {
            $checks = $this->integrityChecker->check();
        } catch (\Throwable $e) {
            $io->error('Contrôle impossible : '.$e->getMessage());
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
        $io->success('Les cardinalités documentaires sont cohérentes avec V3.');
        return Command::SUCCESS;
    }
}
