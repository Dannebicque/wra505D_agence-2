<?php

namespace App\Command\Celcat;

use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureDiplome;
use App\Service\Celcat\CelcatSource;
use App\Service\Celcat\CelcatSynchronizer;
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
        private readonly CelcatSource $lecteur,
        private readonly CelcatSynchronizer $synchroniseur,
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

        if (!$this->lecteur->estConfigure()) {
            $io->error('Celcat n\'est pas configuré : renseigner CELCAT_DSN.');

            return Command::FAILURE;
        }

        $depots = $this->entityManager->getRepository(StructureAnneeUniversitaire::class);
        $annee = null !== $input->getOption('annee')
            ? $depots->find((int) $input->getOption('annee'))
            : $depots->findOneBy(['actif' => true]);
        if (null === $annee) {
            $io->error('Aucune année universitaire trouvée.');

            return Command::FAILURE;
        }

        $departements = null !== $input->getOption('departement')
            ? [(int) $input->getOption('departement')]
            : $this->departementsCelcat();
        if ([] === $departements) {
            $io->warning('Aucun diplôme n\'a de code de département Celcat.');

            return Command::SUCCESS;
        }

        $io->title('Synchronisation Celcat, année '.$annee->getLibelle());

        foreach ($departements as $departement) {
            $rapport = $this->synchroniseur->synchroniser($annee, $departement);

            $io->section('Département Celcat '.$departement);
            $io->definitionList(
                ['Créneaux créés' => $rapport->crees],
                ['Créneaux mis à jour' => $rapport->misAJour],
                ['Créneaux supprimés' => $rapport->supprimes],
            );

            if ([] !== $rapport->conserves) {
                $io->warning(count($rapport->conserves).' créneau(x) retiré(s) de Celcat mais conservé(s), car des absences y sont rattachées.');
            }

            // Ces codes existent dans Celcat mais sont inconnus d'uniServices : tant qu'ils
            // ne sont pas renseignés, les créneaux n'y sont pas rattachés.
            foreach ([
                'Groupes sans code Apogée correspondant' => $rapport->groupesInconnus,
                'Enseignants sans numéro Harpège correspondant' => $rapport->personnelsInconnus,
                'Matières sans code d\'enseignement correspondant' => $rapport->modulesInconnus,
            ] as $titre => $codes) {
                if ([] !== $codes) {
                    $io->note($titre.' : '.implode(', ', array_keys($codes)));
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function departementsCelcat(): array
    {
        $codes = [];
        foreach ($this->entityManager->getRepository(StructureDiplome::class)->findAll() as $diplome) {
            if (null !== $diplome->getCodeCelcatDepartement()) {
                $codes[$diplome->getCodeCelcatDepartement()] = $diplome->getCodeCelcatDepartement();
            }
        }

        return array_values($codes);
    }
}
