<?php

namespace App\Command\CopyBdd;

use IntranetBundle\Entity\Previsionnel\Previsionnel;
use App\Repository\PersonnelRepository;
use App\Repository\ScolEnseignementRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Utils\LooseValue;

#[AsCommand(
    name: 'copy:transfert-bdd:previsionnel',
    description: 'Copie des previ',
)]
class CopyTransfertBddPrevisionnelCommand extends Command
{
    protected Connection $em;

    /** @var array<int|string, \App\Entity\Users\Personnel> */
    protected array $tPersonnels = [];
    /** @var array<int|string, \App\Entity\Structure\StructureAnneeUniversitaire> */
    protected array $tAnneeUniversitaire = [];
    /** @var array<int|string, \App\Entity\Scolarite\ScolEnseignement> */
    protected array $tEnseignements = [];

    protected SymfonyStyle $io;
    protected string $base_url;
    private ScolEnseignementRepository $scolEnseignementRepository;


    public function __construct(
        protected EntityManagerInterface   $entityManager,
        #[Target('copy')] Connection $copyConnection,
        protected HttpClientInterface      $httpClient,
        ParameterBagInterface              $params,
        PersonnelRepository                $personnelRepository,
        ScolEnseignementRepository         $scolEnseignementRepository,
        StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository
    ) {
        parent::__construct();
        $this->tPersonnels = array_filter($personnelRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Users\Personnel);
        $this->tAnneeUniversitaire = array_filter($structureAnneeUniversitaireRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureAnneeUniversitaire);
        $this->base_url = $params->get('URL_INTRANET_V3');
        $this->httpClient = HttpClient::create([
            'verify_peer' => false,
            'verify_host' => false,
        ]);
        $this->em = $copyConnection;
        $this->scolEnseignementRepository = $scolEnseignementRepository;
    }

    protected function configure(): void
    {
    }

    private function effacerTables(): void
    {
        // vider les tables de destination et les réinitialiser
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE previsionnel');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->effacerTables();

        $this->addPrevisEnseignement();
        $this->addPrevisRessource();
        $this->addPrevisSae();

        $this->io->success('Processus de recopie terminé.');

        return Command::SUCCESS;
    }

    /** @return array<array-key, array<array-key, mixed>> */
    private function fetchAllPages(string $url): array
    {
        $page = 1;
        $limit = 50;
        $allData = [];

        do {
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'page' => $page,
                    'limit' => $limit,
                ],
                'timeout' => 600,
            ]);
            $data = LooseValue::rows(json_decode($response->getContent(), true) ?? []);
            $allData = array_merge($allData, $data);
            $page++;
        } while (count($data) === $limit);

        return $allData;
    }

    private function addPrevisEnseignement(): int
    {
        $previs = $this->fetchAllPages($this->base_url . '/previsionnels/matiere');

        foreach ($previs as $previ) {
            $enseignement = $this->scolEnseignementRepository->findOneBy(['oldId' => LooseValue::row($previ['matiere'])['id'], 'type' => LooseValue::row($previ['matiere'])['type']]);

            $previsionnel = new Previsionnel();
            if (array_key_exists(LooseValue::key($previ['personnel']), $this->tPersonnels)) {
                $previsionnel->setPersonnel($this->tPersonnels[LooseValue::key($previ['personnel'])]);
            }
            if (array_key_exists(LooseValue::key($previ['annee']), $this->tAnneeUniversitaire)) {
                $previsionnel->setAnneeUniversitaire($this->tAnneeUniversitaire[LooseValue::key($previ['annee'])]);
            }
            $previsionnel->setEnseignement($enseignement);
            $previsionnel->setReferent(LooseValue::bool($previ['referent']));
            $previsionnel->setHeures([
                'CM' => LooseValue::float($previ['nbHCm']),
                'TD' => LooseValue::float($previ['nbHTd']),
                'TP' => LooseValue::float($previ['nbHTp']),
                'Projet' => 0,
            ]);
            $previsionnel->setGroupes([
                'CM' => LooseValue::int($previ['nbGrCm']),
                'TD' => LooseValue::int($previ['nbGrTd']),
                'TP' => LooseValue::int($previ['nbGrTp']),
                'Projet' => 0,
            ]);

            $this->entityManager->persist($previsionnel);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function addPrevisRessource(): int
    {
        $previs = $this->fetchAllPages($this->base_url . '/previsionnels/ressource');

        foreach ($previs as $previ) {
            $enseignement = $this->scolEnseignementRepository->findOneBy(['oldId' => LooseValue::row($previ['matiere'])['id'], 'type' => LooseValue::row($previ['matiere'])['type']]);

            $previsionnel = new Previsionnel();
            if (array_key_exists(LooseValue::key($previ['personnel']), $this->tPersonnels)) {
                $previsionnel->setPersonnel($this->tPersonnels[LooseValue::key($previ['personnel'])]);
            }
            if (array_key_exists(LooseValue::key($previ['annee']), $this->tAnneeUniversitaire)) {
                $previsionnel->setAnneeUniversitaire($this->tAnneeUniversitaire[LooseValue::key($previ['annee'])]);
            }
            $previsionnel->setEnseignement($enseignement);
            $previsionnel->setReferent(LooseValue::bool($previ['referent']));
            $previsionnel->setHeures([
                'CM' => LooseValue::float($previ['nbHCm']),
                'TD' => LooseValue::float($previ['nbHTd']),
                'TP' => LooseValue::float($previ['nbHTp']),
                'Projet' => 0,
            ]);
            $previsionnel->setGroupes([
                'CM' => LooseValue::int($previ['nbGrCm']),
                'TD' => LooseValue::int($previ['nbGrTd']),
                'TP' => LooseValue::int($previ['nbGrTp']),
                'Projet' => 0,
            ]);

            $this->entityManager->persist($previsionnel);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function addPrevisSae(): int
    {
        $previs = $this->fetchAllPages($this->base_url . '/previsionnels/sae');

        foreach ($previs as $previ) {
            $enseignement = $this->scolEnseignementRepository->findOneBy(['oldId' => LooseValue::row($previ['matiere'])['id'], 'type' => LooseValue::row($previ['matiere'])['type']]);

            $previsionnel = new Previsionnel();
            if (array_key_exists(LooseValue::key($previ['personnel']), $this->tPersonnels)) {
                $previsionnel->setPersonnel($this->tPersonnels[LooseValue::key($previ['personnel'])]);
            }
            if (array_key_exists(LooseValue::key($previ['annee']), $this->tAnneeUniversitaire)) {
                $previsionnel->setAnneeUniversitaire($this->tAnneeUniversitaire[LooseValue::key($previ['annee'])]);
            }
            $previsionnel->setEnseignement($enseignement);
            $previsionnel->setReferent(LooseValue::bool($previ['referent']));
            $previsionnel->setHeures([
                'CM' => LooseValue::float($previ['nbHCm']),
                'TD' => LooseValue::float($previ['nbHTd']),
                'TP' => LooseValue::float($previ['nbHTp']),
                'Projet' => 0,
            ]);
            $previsionnel->setGroupes([
                'CM' => LooseValue::int($previ['nbGrCm']),
                'TD' => LooseValue::int($previ['nbGrTd']),
                'TP' => LooseValue::int($previ['nbGrTp']),
                'Projet' => 0,
            ]);

            $this->entityManager->persist($previsionnel);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
