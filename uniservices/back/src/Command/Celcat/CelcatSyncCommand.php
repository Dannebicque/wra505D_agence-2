<?php

declare(strict_types=1);

namespace App\Command\Celcat;

use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureDiplome;
use App\Service\Celcat\CelcatSource;
use App\Service\Celcat\CelcatSynchronizer;
use App\Utils\LooseValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:celcat:sync',
    description: 'Synchronise l\'emploi du temps avec Celcat',
)]
final class CelcatSyncCommand extends Command
{
    public function __construct(
        private readonly CelcatSource $source,
        private readonly CelcatSynchronizer $synchronizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('annee', null, InputOption::VALUE_REQUIRED, 'Identifiant de l\'année universitaire, l\'active par défaut')
            ->addOption('departement', null, InputOption::VALUE_REQUIRED, 'Code Celcat du département, tous les diplômes par défaut');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->source->isConfigured()) {
            $io->error('Celcat n\'est pas configuré : renseigner CELCAT_DSN.');

            return Command::FAILURE;
        }

        $repositories = $this->entityManager->getRepository(StructureAnneeUniversitaire::class);
        $academicYear = null !== $input->getOption('annee')
            ? $repositories->find(LooseValue::castInt($input->getOption('annee')))
            : $repositories->findOneBy(['actif' => true]);
        if (null === $academicYear) {
            $io->error('Aucune année universitaire trouvée.');

            return Command::FAILURE;
        }

        $departments = null !== $input->getOption('departement')
            ? [LooseValue::castInt($input->getOption('departement'))]
            : $this->celcatDepartments();
        if ([] === $departments) {
            $io->warning('Aucun diplôme n\'a de code de département Celcat.');

            return Command::SUCCESS;
        }

        $io->title('Synchronisation Celcat, année '.$academicYear->getLibelle());
        $io->text($this->synchronizer->synchronizeCalendar($academicYear).' semaines reprises dans le calendrier.');

        foreach ($departments as $department) {
            $report = $this->synchronizer->synchronize($academicYear, $department);

            $io->section('Département Celcat '.$department);
            $io->definitionList(
                ['Créneaux créés' => $report->created],
                ['Créneaux mis à jour' => $report->updated],
                ['Créneaux supprimés' => $report->deleted],
            );

            if ([] !== $report->kept) {
                $io->warning(count($report->kept).' créneau(x) retiré(s) de Celcat mais conservé(s), car des absences y sont rattachées.');
            }

            // Ces codes existent dans Celcat mais sont inconnus d'uniServices : tant qu'ils
            // ne sont pas renseignés, les créneaux n'y sont pas rattachés.
            foreach ([
                'Groupes sans code Apogée correspondant' => $report->unknownGroups,
                'Enseignants sans numéro Harpège correspondant' => $report->unknownStaff,
                'Matières sans code d\'enseignement correspondant' => $report->unknownModules,
            ] as $title => $codes) {
                if ([] !== $codes) {
                    $io->note($title.' : '.implode(', ', array_keys($codes)));
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function celcatDepartments(): array
    {
        $codes = [];
        foreach ($this->entityManager->getRepository(StructureDiplome::class)->findAll() as $degree) {
            if (null !== $degree->getCodeCelcatDepartement()) {
                $codes[$degree->getCodeCelcatDepartement()] = $degree->getCodeCelcatDepartement();
            }
        }

        return array_values($codes);
    }
}
