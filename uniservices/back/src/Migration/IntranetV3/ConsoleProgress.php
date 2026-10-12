<?php

declare(strict_types=1);

namespace App\Migration\IntranetV3;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Affiche l'avancement d'une migration V3 en console : une ligne par étape, une barre par lot de
 * lignes. Branché sur MigrationContext par ses rappels.
 */
final class ConsoleProgress
{
    private ?ProgressBar $bar = null;

    private ?string $currentMigration = null;

    private int $index = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly int $migrationCount,
        private readonly bool $enabled,
    ) {
    }

    public function context(bool $dryRun): MigrationContext
    {
        return new MigrationContext(
            dryRun: $dryRun,
            verbose: $this->output->isVerbose(),
            onMigrationStart: $this->migrationStarted(...),
            onMigrationFinish: $this->migrationFinished(...),
            onProgressStart: $this->start(...),
            onProgressAdvance: $this->advance(...),
            onProgressFinish: $this->finish(...),
        );
    }

    public function finish(): void
    {
        if ($this->bar === null) {
            return;
        }
        $this->bar->finish();
        $this->output->writeln('');
        $this->bar = null;
    }

    private function migrationStarted(string $name): void
    {
        $this->currentMigration = $name;
        ++$this->index;
        if ($this->enabled) {
            $this->output->writeln('');
            $this->output->writeln(sprintf('<info>[%d/%d] %s</info>', $this->index, $this->migrationCount, $name));
        }
    }

    private function migrationFinished(string $name): void
    {
        if ($this->enabled) {
            $this->output->writeln(sprintf('<info>      %s terminé</info>', $name));
        }
    }

    private function start(string $label, int $total): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->finish();

        $this->bar = new ProgressBar($this->output, $total);
        $this->bar->setFormat($total > 0
            ? '      %message% — %current%/%max% [%bar%] %percent:3s%%'
            : '      %message% — %current% lignes traitées');
        $this->bar->setMessage(sprintf('%s · %s', $this->currentMigration ?? 'migration', $label));
        $this->bar->start();
    }

    private function advance(int $step): void
    {
        $this->bar?->advance($step);
    }
}
