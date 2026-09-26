<?php

namespace App\Command\CopyBdd;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Scolarite\ScolBac;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Users\Etudiant;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Repository\EtudiantRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
use App\Repository\Structure\StructureDepartementRepository;
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
    name: 'copy:transfert-bdd:scolarite',
    description: 'Add a short description for your command',
)]
class CopyTransfertBddScolariteCommand extends Command
{
    protected Connection $em;

    /** @var array<int|string, Etudiant> */
    protected array $tEtudiants = [];
    /** @var array<int|string, StructureAnneeUniversitaire> */
    protected array $tAnneeUniversitaire = [];
    /** @var array<int|string, StructureSemestre> */
    protected array $tSemestres = [];
    /** @var array<int|string, StructureDepartement> */
    protected array $tDepartements = [];

    /** @var array<int|string, StructureGroupe> */
    protected array $tGroupes = [];
    protected string $base_url;


    protected SymfonyStyle $io;

    public function __construct(
        protected EntityManagerInterface      $entityManager,
        #[Target('copy')] Connection $copyConnection,
        StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository,
        StructureSemestreRepository           $structureSemestreRepository,
        EtudiantRepository                    $etudiantRepository,
        protected HttpClientInterface         $httpClient,
        StructureDepartementRepository $structureDepartementRepository,
        StructureGroupeRepository $structureGroupeRepository,
        ParameterBagInterface                 $params
    ) {
        parent::__construct();
        $this->em = $copyConnection;
        $this->tAnneeUniversitaire = array_filter($structureAnneeUniversitaireRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof StructureAnneeUniversitaire);
        $this->tSemestres = array_filter($structureSemestreRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof StructureSemestre);
        $this->tEtudiants = array_filter($etudiantRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof Etudiant);
        $this->tDepartements = array_filter($structureDepartementRepository->findAllByIdArray(), static fn (mixed $entity): bool => $entity instanceof StructureDepartement);
        $this->tGroupes = array_filter($structureGroupeRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof StructureGroupe);
        // url intranet
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
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE etudiant_scolarite');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE etudiant_scolarite_semestre');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE etudiant_scolarite_structure_annee');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE etudiant_scolarite_semestre_structure_groupe');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->effacerTables();
        $this->addEtudiantScolarite();
        $this->addScolBac();

        $this->io->success('Processus de recopie terminé.');

        return Command::SUCCESS;
    }

    private function addEtudiantScolarite(): void
    {
        ini_set('memory_limit', '2G');
        $sql = 'SELECT * FROM etudiant WHERE semestre_id IS NOT NULL and annee_sortie = 0';
        $etudiants = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($etudiants as $etu) {
            $response = $this->httpClient->request('GET', $this->base_url . '/etudiant/' . LooseValue::castString($etu['id']));
            $scolarites = json_decode($response->getContent(), true);

            if ($scolarites && isset($this->tEtudiants[LooseValue::key($etu['id'])])) {
                $scolarites = LooseValue::rows($scolarites);
                foreach ($scolarites as $scol) {
                    if (!array_key_exists(LooseValue::key($scol['annee']), $this->tAnneeUniversitaire)) {
                        continue;
                    }

                    $scolarite = new EtudiantScolarite();
                    $scolarite->setUuid(UuidV4::v4());
                    $scolarite->setEtudiant($this->tEtudiants[LooseValue::key($etu['id'])]);
                    $scolarite->setAnneeUniversitaire($this->tAnneeUniversitaire[LooseValue::key($scol['annee'])]);
                    if ($this->tAnneeUniversitaire[LooseValue::key($scol['annee'])]->isActif()) {
                        $scolarite->setActif(true);
                    }

                    // Définir les propriétés globales de la scolarité
                    $bilan = LooseValue::row($scol['bilan'] ?? []);
                    $scolarite->setMoyenne(isset($bilan['moyenne']) ? round(LooseValue::float($bilan['moyenne']), 2) : 0);
                    $scolarite->setNbAbsences(LooseValue::int($bilan['nbAbsences'] ?? 0));

                    $scolarite->setCommentaire(LooseValue::nullableString($bilan['commentaire'] ?? ''));

                    // Set decision from bilan data (convert string to boolean if needed)
                    $decision = $bilan['decision'] ?? null;
                    if ($decision === 'V') {
                        $scolarite->setDecision(true);
                    } elseif ($decision === null) {
                        $scolarite->setDecision(null);
                    } else {
                        $scolarite->setDecision(false);
                    }

                    // Set proposition if available in the last semester
                    $semestres = LooseValue::rows($scol['semestres'] ?? []);
                    if (!empty($semestres)) {
                        $lastSemester = end($semestres);
                        if (isset($lastSemester['proposition'])) {
                            // If the proposition is for the next year (like "DUT"), find the appropriate year
                            foreach ($this->tAnneeUniversitaire as $annee) {
                                if ($annee->getLibelle() === $lastSemester['proposition']) {
                                    // L'origine passait ici l'année civile (getAnnee(), un entier) à
                                    // setProposition(), qui attend une StructureAnnee : l'import plantait
                                    // sur une TypeError. Fiche E19.
                                    throw new \LogicException('Proposition de scolarité V3 non reprise, voir la fiche E19.');
                                }
                            }
                        }
                    }

                    $scolarite->setOrdre(LooseValue::int($scol['ordre'] ?? count($scolarites)));

                    foreach ($this->tDepartements as $departement) {
                        if ($departement->getOldId() === $etu['departement_id']) {
                            $scolarite->setDepartement($departement);
                            break;
                        }
                    }

                    // Ajouter les semestres
                    // Garder trace des semestres déjà créés pour cette scolarité (indexés par id de StructureSemestre)
                    $semestresCrees = [];
                    foreach ($semestres as $semestre) {
                        foreach ($this->tSemestres as $semestreDest) {
                            if ($semestreDest->getOldId() === $semestre['id']) {
                                $etudiantScolSemestre = new EtudiantScolariteSemestre();
                                $etudiantScolSemestre->setScolarite($scolarite);
                                $etudiantScolSemestre->setSemestre($semestreDest);
                                foreach (LooseValue::rows($semestre['groupes'] ?? []) as $groupe) {
                                    if (isset($this->tGroupes[LooseValue::key($groupe['id'])])) {
                                        $etudiantScolSemestre->addGroupe($this->tGroupes[LooseValue::key($groupe['id'])]);
                                    }
                                }

                                // Set decision from semestre data (convert string to boolean if needed)
                                $decision = $semestre['decision'] ?? null;
                                if ($decision === 'V') {
                                    $etudiantScolSemestre->setDecision(true);
                                } elseif ($decision === null) {
                                    $etudiantScolSemestre->setDecision(null);
                                } else {
                                    $etudiantScolSemestre->setDecision(false);
                                }

                                // Set proposition if available
                                if (isset($semestre['proposition'])) {
                                    // Find the appropriate semester for the proposition
                                    foreach ($this->tSemestres as $propositionSemestre) {
                                        if ($propositionSemestre->getLibelle() === $semestre['proposition']) {
                                            $etudiantScolSemestre->setProposition($propositionSemestre);
                                            break;
                                        }
                                    }
                                }

                                // Set moyenne from semestre data
                                $etudiantScolSemestre->setMoyenne(isset($semestre['moyenne']) ? round(LooseValue::float($semestre['moyenne']), 2) : 0);

                                $this->entityManager->persist($etudiantScolSemestre);
                                $semestresCrees[$semestreDest->getId()] = true;
                            }
                        }
                    }

                    // S'assurer que les 2 semestres de chaque année sont créés.
                    // Si un semestre a été créé depuis les données, on vérifie que l'autre semestre
                    // de la même StructureAnnee existe aussi. Sinon, on crée un EtudiantScolariteSemestre vide.
                    foreach ($semestresCrees as $semestreCreéId => $_) {
                        foreach ($this->tSemestres as $semestreSource) {
                            if ($semestreSource->getId() !== $semestreCreéId) {
                                continue;
                            }
                            $annee = $semestreSource->getAnnee();
                            if ($annee === null) {
                                continue;
                            }
                            // Parcourir tous les semestres de la même StructureAnnee
                            foreach ($annee->getSemestres() as $semestreJumeau) {
                                if (!isset($semestresCrees[$semestreJumeau->getId()])) {
                                    // Ce semestre de l'année n'a pas encore été créé : on l'ajoute vide
                                    $etudiantScolSemestreVide = new EtudiantScolariteSemestre();
                                    $etudiantScolSemestreVide->setScolarite($scolarite);
                                    $etudiantScolSemestreVide->setSemestre($semestreJumeau);
                                    $this->entityManager->persist($etudiantScolSemestreVide);
                                    $semestresCrees[$semestreJumeau->getId()] = true;
                                }
                            }
                        }
                    }

                    $this->entityManager->persist($scolarite);
                }
            }
        }
        $this->entityManager->flush();
    }

    private function addScolBac(): void
    {
        $sql = 'SELECT * FROM bac WHERE 1';
        $bacs = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($bacs as $bac) {
            $scolBac = new ScolBac();
            $scolBac->setLibelle(LooseValue::string($bac['libelle']));
            $scolBac->setLibelleLong(LooseValue::string($bac['libelle_long']));
            $scolBac->setOldId(LooseValue::nullableInt($bac['id']));
            $scolBac->setCodeApogee(LooseValue::nullableString($bac['code_apogee']));
            $this->entityManager->persist($scolBac);
        }
        $this->entityManager->flush();
    }
}
