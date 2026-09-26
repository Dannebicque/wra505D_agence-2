<?php

namespace App\Command\CopyBdd;

use App\Entity\Edt\EdtEvent;
use App\Repository\PersonnelRepository;
use App\Repository\ScolEnseignementRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
use App\Repository\Structure\StructureGroupeRepository;
use App\Repository\Structure\StructureSemestreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\UuidV4;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Utils\LooseValue;

#[AsCommand(
    name: 'copy:transfert-bdd:edt',
    description: 'Add a short description for your command',
)]
class CopyTransfertBddEdtCommand extends Command
{
    protected Connection $em;

    /** @var array<int|string, \App\Entity\Scolarite\ScolEnseignement> */
    protected array $tMatieres = [];
    /** @var array<int|string, \App\Entity\Users\Personnel> */
    protected array $tPersonnels = [];
    /** @var array<int|string, \App\Entity\Structure\StructureSemestre> */
    protected array $tSemestres = [];
    /** @var array<int|string, \App\Entity\Structure\StructureAnneeUniversitaire> */
    protected array $tAnneesUniversitaires = [];
    /** @var array<int|string, \App\Entity\Structure\StructureGroupe> */
    protected array $tGroupes = [];
    protected string $base_url;


    protected SymfonyStyle $io;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        #[Target('copy')] Connection $copyConnection,
        PersonnelRepository              $personnelRepository,
        StructureSemestreRepository      $structureSemestreRepository,
        StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository,
        StructureGroupeRepository $structureGroupeRepository,
        ScolEnseignementRepository       $scolEnseignementRepository,
        protected HttpClientInterface    $httpClient,
        ParameterBagInterface            $params
    ) {
        parent::__construct();
        $this->em = $copyConnection;
        $this->tPersonnels = array_filter($personnelRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Users\Personnel);
        $this->tSemestres = array_filter($structureSemestreRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureSemestre);
        $this->tAnneesUniversitaires = array_filter($structureAnneeUniversitaireRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureAnneeUniversitaire);
        $this->tGroupes = array_filter($structureGroupeRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureGroupe);
        $matieres = $scolEnseignementRepository->findAll();

        foreach ($matieres as $matiere) {
            $this->tMatieres[$matiere->getType()->value . '_' . $matiere->getOldId()] = $matiere;
        }

        $this->base_url = $params->get('URL_INTRANET_V3');
        $this->httpClient = HttpClient::create([
            'verify_peer' => false,
            'verify_host' => false,
        ]);

    }

    protected function configure(): void
    {
    }

    private function effacerTables(): void
    {
        // vider les tables de destination et les réinitialiser
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE edt_event');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->effacerTables();
        $this->addEdtEventIntranet();
        // $this->addEdtEventCelcat();

        $this->io->success('Processus de recopie terminé.');

        return Command::SUCCESS;
    }

    private function addEdtEventIntranet(): void
    {
        $reponses = $this->httpClient->request('GET', $this->base_url . '/edt-intranet');
        $edts = $reponses->toArray();
        foreach ($edts as $ed) {
            if (array_key_exists(LooseValue::key($ed['prof']), $this->tPersonnels) && array_key_exists(LooseValue::key($ed['matiere']), $this->tMatieres)) {
                $edt = new EdtEvent();
                $edt->setUuid(UuidV4::v4());
                $edt->setDate(new \DateTime(LooseValue::string($ed['date'])));
                $edt->setDebut(new \DateTime(LooseValue::string($ed['debut'])));
                $edt->setFin(new \DateTime(LooseValue::string($ed['fin'])));
                $edt->setSalle(LooseValue::string($ed['salle']));
                $edt->setPersonnel($this->tPersonnels[LooseValue::key($ed['prof'])]);
                $edt->setLibPersonnel(LooseValue::nullableString($ed['libprof']));
                $edt->setCodePersonnel(LooseValue::nullableString($ed['codeRh']));
                $edt->setGroupe($this->tGroupes[LooseValue::key($ed['groupe'])] ?? null);
                $edt->setType(LooseValue::nullableString($ed['type']));
                $edt->setCouleur(LooseValue::nullableString($ed['couleur']));
                $edt->setEvaluation(LooseValue::bool($ed['evaluation']));
                //$edt->setCodeGroupe($this->tGroupes[$ed['groupe']]->getCodeApogee());
                $edt->setCodeModule(LooseValue::nullableString($this->tMatieres[LooseValue::key($ed['matiere'])]->getCodeApogee()));
                $edt->setEnseignement($this->tMatieres[LooseValue::key($ed['matiere'])]);
                $edt->setJour(LooseValue::nullableInt($ed['jour']));
                // $edt->setLibGroupe($this->tGroupes[$ed['groupe']]->getLibelle());
                $edt->setLibModule(LooseValue::nullableString($this->tMatieres[LooseValue::key($ed['matiere'])]->getLibelle()));
                $edt->setSemestre($this->tSemestres[LooseValue::key($ed['semestre'])]);
                $edt->setSemaineFormation(LooseValue::nullableInt($ed['semaine']));
                $edt->setAnneeUniversitaire($this->tAnneesUniversitaires[LooseValue::key($ed['anneeUniversitaire'])]);

                $this->entityManager->persist($edt);
            }
        }
        $this->entityManager->flush();
    }
}
