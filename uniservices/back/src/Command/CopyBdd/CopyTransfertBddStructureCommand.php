<?php

namespace App\Command\CopyBdd;

use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureCalendrier;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Structure\StructureTypeDiplome;
use App\Entity\Structure\StructureUe;
use App\Entity\Apc\ApcApprentissageCritique;
use App\Entity\Apc\ApcCompetence;
use App\Enum\TypeGroupeEnum;
use App\Repository\Apc\ApcApprentissageCritiqueRepository;
use App\Repository\Apc\ApcCompetenceRepository;
use App\Repository\Apc\ApcParcoursRepository;
use App\Repository\PersonnelRepository;
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
use Symfony\Component\Uid\UuidV4;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'copy:transfert-bdd:structure',
    description: 'Transfert la structure des départements et diplômes de l\'intranet V3 au format V4. Supprime toutes les données de la table ScoleEnseignement',
)]
class CopyTransfertBddStructureCommand extends Command
{
    protected Connection $em;

    /** @var array<int|string, StructureDepartement> */
    protected array $tDepartements = [];
    /** @var array<int|string, StructureAnneeUniversitaire> */
    protected array $tAnneeUniversitaire = [];
    /** @var array<int|string, StructureTypeDiplome> */
    protected array $tTypeDiplomes = [];
    /** @var array<int|string, StructureDiplome> */
    protected array $tDiplomes = [];
    /** @var array<int|string, StructureAnnee> */
    protected array $tAnnees = [];
    /** @var array<int|string, StructureSemestre> */
    protected array $tSemestres = [];
    /** @var array<int|string, mixed> */
    protected array $tMatieres = [];
    /** @var array<int|string, ApcCompetence> */
    protected array $tCompetences = [];
    /** @var array<int|string, StructureUe> */
    protected array $tUes = [];
    /** @var array<int|string, array<int|string, StructureUe>> */
    protected array $tSemestreUes = [];
    /** @var array<int|string, ApcApprentissageCritique> */
    protected array $tApprentissages = [];

    protected SymfonyStyle $io;
    protected string $base_url;
    private StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository;
    private PersonnelRepository $personnelRepository;
    private ApcParcoursRepository $apcParcoursRepository;


    public function __construct(
        protected EntityManagerInterface   $entityManager,
        #[Target('copy')] Connection $copyConnection,
        ApcApprentissageCritiqueRepository $apcApprentissageCritiqueRepository,
        ApcCompetenceRepository            $apcCompetenceRepository,
        StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository,
        ApcParcoursRepository             $apcParcoursRepository,
        PersonnelRepository              $personnelRepository,
        protected HttpClientInterface      $httpClient,
        ParameterBagInterface              $params
    ) {
        parent::__construct();
        $this->tCompetences = array_filter($apcCompetenceRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof ApcCompetence);
        $this->tApprentissages = array_filter($apcApprentissageCritiqueRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof ApcApprentissageCritique);
        $this->structureAnneeUniversitaireRepository = $structureAnneeUniversitaireRepository;
        $this->apcParcoursRepository = $apcParcoursRepository;
        $this->personnelRepository = $personnelRepository;
        $this->base_url = $params->get('URL_INTRANET_V3');
        $this->httpClient = HttpClient::create([
            'verify_peer' => false,
            'verify_host' => false,
        ]);
        $this->em = $copyConnection;
    }

    protected function configure(): void
    {
        //option --force
        $this->addOption('force', null, null, 'Force la suppression des données');
    }

    private function effacerTables(): void
    {
        // vider les tables de destination et les réinitialiser
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_calendrier');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_type_diplome');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_departement');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_diplome');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_diplome_annee_universitaire');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_pn');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_annee');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_semestre');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_ue');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_annee_universitaire');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE scol_enseignement');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE scol_enseignement_ue');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_groupe');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_groupe_structure_semestre');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $this->io->warning('Cette commande va supprimer les données de la structure ainsi que des matières, ressources et SAE.');
            if (!$this->io->confirm('Cette commande va supprimer les données de la structure ainsi que des matières, ressources et SAE. Confirmer ?', false)) {
                $this->io->warning('Command execution aborted.');
                return Command::FAILURE;
            }
        }


        $this->effacerTables();
        $this->addAnneeUniversitaire();

        //        // Départements
        $this->addTypeDiplome();
        $this->addDepartements();
        $this->addDiplomes();
        $this->addAnnee();
        $this->addSemestre();
        $this->addUe();
        $this->addGroupes();

        $this->io->success('Processus de recopie terminé.');

        return Command::SUCCESS;
    }

    public function addAnneeUniversitaire(): void
    {
        $sql = "SELECT * FROM annee_universitaire";
        $annees = $this->em->executeQuery($sql)->fetchAllAssociative();
        $anneeActive = null;
        foreach ($annees as $annee) {
            $anneeUniversitaire = new StructureAnneeUniversitaire();
            $anneeUniversitaire->setLibelle(LegacyValue::string($annee['libelle']));
            $anneeUniversitaire->setAnnee(LegacyValue::int($annee['annee']));
            $anneeUniversitaire->setActif(LegacyValue::bool($annee['active']));
            $anneeUniversitaire->setCommentaire(LegacyValue::nullableString($annee['commentaire']));
            $anneeUniversitaire->setOldId(LegacyValue::nullableInt($annee['id']));
            if ($annee['active']) {
                $anneeActive = $anneeUniversitaire;
            }

            $this->tAnneeUniversitaire[LegacyValue::key($annee['id'])] = $anneeUniversitaire;

            $this->entityManager->persist($anneeUniversitaire);
            $this->io->info('Année Universitaire : ' . LegacyValue::castString($annee['libelle']) . ' ajouté pour insertion');
        }

        $this->entityManager->flush();

        if (null === $anneeActive) {
            throw new \LogicException('Aucune année universitaire active.');
        }
        $sql = "SELECT * FROM calendrier WHERE annee_universitaire_id = " . $anneeActive->getOldId();
        $annees = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($annees as $annee) {
            $calendrier = new StructureCalendrier();
            $calendrier->setAnneeUniversitaire($anneeActive);
            $calendrier->setDateLundi(new \DateTime(LegacyValue::string($annee['date_lundi'])));
            $calendrier->setSemaineFormation(LegacyValue::int($annee['semaine_formation']));
            $calendrier->setSemaineReelle(LegacyValue::int($annee['semaine_reelle']));
            $this->entityManager->persist($calendrier);
        }
        $this->entityManager->flush();
    }

    public function addDepartements(): void
    {
        $sql = "SELECT * FROM departement WHERE actif = 1";
        $departements = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($departements as $dept) {
            $departement = new StructureDepartement();
            $departement->setUuid(new UuidV4());
            $departement->setLibelle(LegacyValue::string($dept['libelle']));
            $departement->setLogoName(LegacyValue::nullableString($dept['logo_name']));
            $departement->setTelContact(LegacyValue::nullableString($dept['tel_contact']));
            $departement->setCouleur(LegacyValue::nullableString($dept['couleur']));
            $departement->setSiteWeb(LegacyValue::nullableString($dept['site_web']));
            $departement->setDescription(LegacyValue::nullableString($dept['description']));
            $departement->setActif(LegacyValue::bool($dept['actif']));
            $departement->setOpt([
                'materiel' => (bool)$dept['opt_materiel'],
                'edt' => (bool)$dept['opt_edt'],
                'stage' => (bool)$dept['opt_stage'],
            ]);
            $departement->setOldId(LegacyValue::nullableInt($dept['id']));

            $this->tDepartements[LegacyValue::key($dept['id'])] = $departement;

            $this->entityManager->persist($departement);
            $this->io->info('Département : ' . LegacyValue::castString($dept['libelle']) . ' ajouté pour insertion');
        }

        $this->entityManager->flush();
    }

    private function addDiplomes(): void
    {
        $anneeUnivPn = $this->structureAnneeUniversitaireRepository->findOneBy(['actif' => true]);
        if (null === $anneeUnivPn) {
            throw new \LogicException('Année universitaire active introuvable.');
        }
        $sql = "SELECT * FROM diplome WHERE parent_id IS NULL";
        $diplomes = $this->em->executeQuery($sql)->fetchAllAssociative();
        foreach ($diplomes as $dip) {
            if (array_key_exists(LegacyValue::key($dip['departement_id']), $this->tDepartements)) {
                $apcParcours = $this->apcParcoursRepository->findOneBy(['oldId' => $dip['apc_parcours_id']]);

                $diplome = new StructureDiplome();
                $diplome->setDepartement($this->tDepartements[LegacyValue::key($dip['departement_id'])]);
                $diplome->setLibelle(LegacyValue::string($dip['libelle']));
                $diplome->addAnneeUniversitaire($anneeUnivPn);
                $diplome->setSigle(LegacyValue::nullableString($dip['sigle']));
                $diplome->setKeyEduSign(LegacyValue::nullableString($dip['key_edu_sign']));
                $diplome->setVolumeHoraire(LegacyValue::int($dip['volume_horaire']));
                $diplome->setCodeCelcatDepartement(LegacyValue::nullableInt($dip['code_celcat_departement']));
                $diplome->setLogoPartenaire(LegacyValue::nullableString($dip['logo_partenaire']));
                $diplome->setOpt([
                    'nb_jours_saisie_absence' => $dip['opt_nb_jours_saisie'],
                    'supp_absence' => (bool)$dip['opt_suppr_absence'],
                    'anonymat' => (bool)$dip['opt_anonymat'],
                    'commentaire_releve' => (bool)$dip['opt_commentaires_releve'],
                    'espace_perso_visible' => (bool)$dip['opt_espace_perso_visible'],
                    'semaine_visible' => $dip['opt_semaines_visibles'],
                    'certif_qualite' => (bool)$dip['opt_certifie_qualite'],
                    'resp_qualite' => 0,
                    'update_celcat' => (bool)$dip['opt_update_celcat'],
                    'saisie_cm_autorisee' => (bool)$dip['saisie_cm_autorise'],
                ]);
                $diplome->setApogeeCodeDiplome(LegacyValue::nullableString($dip['code_diplome']));
                $diplome->setApogeeCodeVersion(LegacyValue::nullableString($dip['code_version']));
                $diplome->setApogeeCodeDepartement(LegacyValue::nullableString($dip['code_departement']));
                $diplome->setTypeDiplome($this->tTypeDiplomes[LegacyValue::key($dip['type_diplome_id'])]);
                $diplome->setParcours($apcParcours);
                $diplome->setOldId(LegacyValue::nullableInt($dip['id']));
                if ($dip['responsable_diplome_id'] !== null) {
                    $diplome->setResponsableDiplome($this->personnelRepository->findOneBy(['oldId' => $dip['responsable_diplome_id']]));
                }
                if ($dip['assistant_diplome_id'] !== null) {
                    $diplome->setAssistantDiplome($this->personnelRepository->findOneBy(['oldId' => $dip['assistant_diplome_id']]));
                }

                $this->tDiplomes[LegacyValue::key($dip['id'])] = $diplome;

                //ajout d'un PN
                $sql = "SELECT * FROM ppn WHERE diplome_id = " . LegacyValue::castString($dip['id']) . " ORDER BY created DESC LIMIT 1";
                $ppns = $this->em->executeQuery($sql)->fetchAllAssociative();

                $pn = new StructurePn($diplome);
                $pn->setLibelle(LegacyValue::string($ppns[0]['libelle']));
                $pn->setAnneePublication(LegacyValue::int($ppns[0]['annee']));
                $pn->setAnneeUniversitaire($anneeUnivPn);
                $diplome->addPn($pn);

                $this->entityManager->persist($pn);
                $this->entityManager->persist($diplome);
                $this->io->info('Diplôme : ' . LegacyValue::castString($dip['libelle']) . ' ajouté pour insertion');

                $sql = "SELECT * FROM diplome WHERE parent_id = " . LegacyValue::castString($dip['id']);
                $diplomesEnfants = $this->em->executeQuery($sql)->fetchAllAssociative();
                foreach ($diplomesEnfants as $dipE) {
                    $apcParcoursEnfant = $this->apcParcoursRepository->findOneBy(['oldId' => $dipE['apc_parcours_id']]);

                    $diplomeEnfant = new StructureDiplome();
                    $diplomeEnfant->setDepartement($this->tDepartements[LegacyValue::key($dipE['departement_id'])]);
                    $diplomeEnfant->setLibelle(LegacyValue::string($dipE['libelle']));
                    $diplomeEnfant->addAnneeUniversitaire($anneeUnivPn);
                    $diplomeEnfant->setSigle(LegacyValue::nullableString($dipE['sigle']));
                    $diplomeEnfant->setKeyEduSign(LegacyValue::nullableString($dipE['key_edu_sign']));
                    $diplomeEnfant->setVolumeHoraire(LegacyValue::int($dipE['volume_horaire']));
                    $diplomeEnfant->setCodeCelcatDepartement(LegacyValue::nullableInt($dipE['code_celcat_departement']));
                    $diplomeEnfant->setLogoPartenaire(LegacyValue::nullableString($dipE['logo_partenaire']));
                    $diplomeEnfant->setTypeDiplome($this->tTypeDiplomes[LegacyValue::key($dipE['type_diplome_id'])]);
                    $diplomeEnfant->setOldId(LegacyValue::nullableInt($dipE['id']));
                    $diplomeEnfant->setOpt([
                        'nb_jours_saisie_absence' => $dipE['opt_nb_jours_saisie'],
                        'supp_absence' => (bool)$dipE['opt_suppr_absence'],
                        'anonymat' => (bool)$dipE['opt_anonymat'],
                        'commentaire_releve' => (bool)$dipE['opt_commentaires_releve'],
                        'espace_perso_visible' => (bool)$dipE['opt_espace_perso_visible'],
                        'semaine_visible' => $dipE['opt_semaines_visibles'],
                        'certif_qualite' => (bool)$dipE['opt_certifie_qualite'],
                        'resp_qualite' => 0,
                        'update_celcat' => (bool)$dipE['opt_update_celcat'],
                        'saisie_cm_autorisee' => (bool)$dipE['saisie_cm_autorise'],
                    ]);
                    $diplomeEnfant->setApogeeCodeDiplome(LegacyValue::nullableString($dipE['code_diplome']));
                    $diplomeEnfant->setApogeeCodeVersion(LegacyValue::nullableString($dipE['code_version']));
                    $diplomeEnfant->setApogeeCodeDepartement(LegacyValue::nullableString($dipE['code_departement']));
                    $diplomeEnfant->setParent($diplome);
                    $diplomeEnfant->setParcours($apcParcoursEnfant);
                    if ($dipE['responsable_diplome_id'] !== null) {
                        $diplomeEnfant->setResponsableDiplome($this->personnelRepository->findOneBy(['oldId' => $dipE['responsable_diplome_id']]));
                    }
                    if ($dipE['assistant_diplome_id'] !== null) {
                        $diplomeEnfant->setAssistantDiplome($this->personnelRepository->findOneBy(['oldId' => $dipE['assistant_diplome_id']]));
                    }

                    $this->tDiplomes[LegacyValue::key($dipE['id'])] = $diplomeEnfant;

                    //ajout d'un PN
                    $pnE = new StructurePn($diplomeEnfant);
                    $pnE->setLibelle(LegacyValue::string($ppns[0]['libelle']));
                    $pnE->setAnneePublication(LegacyValue::int($ppns[0]['annee']));
                    $pnE->setAnneeUniversitaire($anneeUnivPn);
                    $diplomeEnfant->addPn($pnE);

                    $this->entityManager->persist($pnE);
                    $this->entityManager->persist($diplomeEnfant);
                }
            }
        }

        $this->entityManager->flush();
    }

    private function addAnnee(): void
    {
        $sql = "SELECT * FROM annee";
        $annees = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($annees as $an) {
            if (array_key_exists(LegacyValue::key($an['diplome_id']), $this->tDiplomes) && $this->tDiplomes[LegacyValue::key($an['diplome_id'])]->getPns()->count() > 0) {
                $annee = new StructureAnnee();
                $diplome = $this->tDiplomes[LegacyValue::key($an['diplome_id'])];
                $pns = $diplome->getPns();
                $annee->setLibelle(LegacyValue::string($an['libelle']));
                $annee->setOrdre(LegacyValue::int($an['ordre']));
                $annee->setLibelleLong(LegacyValue::nullableString($an['libelle_long']));
                $annee->setActif((bool)$an['actif']);
                $annee->setCouleur(LegacyValue::nullableString($an['couleur']));
                $annee->setOpt([
                    'alternance' => (bool)$an['opt_alternance'],
                ]);
                $annee->setApogeeCodeEtape(LegacyValue::nullableString($an['code_etape']));
                $annee->setApogeeCodeVersion(LegacyValue::nullableString($an['code_version']));
                foreach ($pns as $pn) {
                    $pn->addAnnee($annee);
                }

                $this->tAnnees[LegacyValue::key($an['id'])] = $annee;

                $this->entityManager->persist($annee);
            }
        }

        $this->entityManager->flush();
    }

    private function addSemestre(): void
    {
        $sql = "SELECT * FROM semestre";
        $semestres = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($semestres as $sem) {
            if (array_key_exists(LegacyValue::key($sem['annee_id']), $this->tAnnees)) {
                $semestre = new StructureSemestre();
                $semestre->setOpt(
                    [
                        'mail_releve' => (bool)$sem['opt_mail_releve'],
                        'mail_modif_note' => (bool)$sem['opt_mail_modification_note'],
                        'dest_mail_releve' => $sem['opt_dest_mail_releve_id'] ?? 0,
                        'dest_mail_modif_note' => $sem['opt_dest_mail_modif_note_id'] ?? 0,
                        'eval_visible' => (bool)$sem['opt_evaluation_visible'],
                        'eval_modif' => (bool)$sem['opt_evaluation_modifiable'],
                        'penalite_absence' => LegacyValue::castFloat($sem['opt_point_penalite_absence']),
                        'mail_absence_resp' => (bool)$sem['opt_mail_absence_resp'],
                        'dest_mail_absence_resp' => $sem['opt_dest_mail_absence_resp_id'] ?? 0,
                        'mail_absence_etudiant' => (bool)$sem['opt_mail_absence_etudiant'],
                        'opt_penalite_absence' => (bool)$sem['opt_penalite_absence'],
                        'mail_assistante_justif_absence' => (bool)$sem['opt_mail_assistante_justificatif_absence'],
                        'bilan_semestre' => (bool)$sem['opt_bilan_semestre'],
                        'rattrapage' => (bool)$sem['opt_rattrapage'],
                        'mail_rattrapage' => $sem['opt_mail_rattrapage'] ?? 0,
                    ]
                );

                $semestre->setAnnee($this->tAnnees[LegacyValue::key($sem['annee_id'])]);
                $semestre->setLibelle(LegacyValue::string($sem['libelle']));
                $semestre->setOrdreAnnee(LegacyValue::int($sem['ordre_annee']));
                $semestre->setOrdreLmd(LegacyValue::int($sem['ordre_lmd']));
                $semestre->setActif((bool)$sem['actif']);
                $semestre->setNbGroupesCm(LegacyValue::castInt($sem['nb_groupes_cm']));
                $semestre->setNbGroupesTd(LegacyValue::castInt($sem['nb_groupes_td']));
                $semestre->setNbGroupesTp(LegacyValue::castInt($sem['nb_groupes_tp']));
                $semestre->setKeyEduSign(LegacyValue::nullableString($sem['id_edu_sign']));
                $semestre->setCodeElement(LegacyValue::nullableString($sem['code_element']));
                $semestre->setOldId(LegacyValue::nullableInt($sem['id']));

                /*
                 *   "id" => 1
      "ppn_actif_id" => 1
      "created" => "2019-12-09 21:38:01"
      "updated" => "2023-10-13 11:17:10"
      "mois_debut" => 9
                 */

                $this->tSemestres[LegacyValue::key($sem['id'])] = $semestre;

                $this->entityManager->persist($semestre);
                $this->io->info('Semestre : ' . LegacyValue::castString($sem['libelle']) . ' ajouté pour insertion');
            }
        }

        $this->entityManager->flush();
    }

    private function addUe(): void
    {
        $sql = "SELECT * FROM ue";
        $ues = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($ues as $u) {
            if (array_key_exists(LegacyValue::key($u['semestre_id']), $this->tSemestres)) {
                $ue = new StructureUe();
                $ue->setSemestre($this->tSemestres[LegacyValue::key($u['semestre_id'])]);
                $ue->setLibelle(LegacyValue::string($u['libelle']));
                $ue->setNumero(LegacyValue::castInt($u['numero_ue']));
                $ue->setCodeElement(LegacyValue::string($u['code_element']));
                $ue->setActif((bool)$u['actif']);
                $ue->setBonification((bool)$u['bonification']);
                $ue->setNbEcts(LegacyValue::castFloat($u['nb_ects'])); //Apc?
                $ue->setOldId(LegacyValue::nullableInt($u['id']));
                $this->tUes[LegacyValue::key($u['id'])] = $ue;
                if ($u['apc_competence_id'] !== null) {
                    if (array_key_exists(LegacyValue::key($u['apc_competence_id']), $this->tCompetences)) {
                        $ue->setCompetence($this->tCompetences[LegacyValue::key($u['apc_competence_id'])]);
                    } else {
                        $this->io->error('Apc compétence ' . LegacyValue::castString($u['apc_competence_id']) . ' non trouvée pour l\'ue ' . LegacyValue::castString($u['libelle']));
                    }
                    $this->tSemestreUes[LegacyValue::key($u['semestre_id'])][LegacyValue::key($u['apc_competence_id'])] = $ue;
                }


                $this->entityManager->persist($ue);
                $this->io->info('UE : ' . LegacyValue::castString($u['libelle']) . ' ajouté pour insertion');
            }
        }

        $this->entityManager->flush();
    }

    private function addTypeDiplome(): void
    {
        $sql = "SELECT * FROM type_diplome";
        $typeD = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($typeD as $type) {
            $typeDiplome = new StructureTypeDiplome();
            $typeDiplome->setLibelle(LegacyValue::string($type['libelle']));
            $typeDiplome->setSigle(LegacyValue::string($type['sigle']));
            $typeDiplome->setApc((bool)$type['apc']);

            $this->tTypeDiplomes[LegacyValue::key($type['id'])] = $typeDiplome;

            $this->entityManager->persist($typeDiplome);
            $this->io->info('Type Diplome : ' . LegacyValue::castString($type['libelle']) . ' ajouté pour insertion');
        }

        $this->entityManager->flush();
    }

    private function addGroupes(): void
    {
        $reponses = $this->httpClient->request('GET', $this->base_url . '/groupes');
        $groupes = $reponses->toArray();
        foreach ($groupes as $groupeArray) {
            /*
             * "id": 9,
"libelle": "CD",
"codeApogee": "3TSR_2TD2",
"ordre": 3,
"typeGroupe": {
"id": 9,
"libelle": "TD",
"defaut": true,
"type": "TD",
"mutualise": false,
"semestre": [
4
]
},
"parcours": null,
"enfants": []
             */
            $groupe = new StructureGroupe();
            $groupe->setLibelle($groupeArray['libelle']);
            $groupe->setCodeApogee(substr($groupeArray['codeApogee'], 0, 25));
            $groupe->setOrdre($groupeArray['ordre']);
            $groupe->setType(TypeGroupeEnum::TYPE_GROUPE_AUTRE); // Default type
            if (array_key_exists('typeGroupe', $groupeArray) && is_array($groupeArray['typeGroupe'])) {
                $type = $groupeArray['typeGroupe']['type'] ?? null;
                if ($type) {
                    $typeUpper = strtoupper(LegacyValue::string($type));
                    if (str_contains($typeUpper, 'TP')) {
                        $groupe->setType(TypeGroupeEnum::TYPE_GROUPE_TP);
                    } elseif (str_contains($typeUpper, 'TD')) {
                        $groupe->setType(TypeGroupeEnum::TYPE_GROUPE_TD);
                    } elseif (str_contains($typeUpper, 'CM')) {
                        $groupe->setType(TypeGroupeEnum::TYPE_GROUPE_CM);
                    } elseif (defined('TypeGroupeEnum::TYPE_GROUPE_' . $typeUpper)) {
                        $type = constant('TypeGroupeEnum::TYPE_GROUPE_' . $typeUpper);
                        if ($type instanceof TypeGroupeEnum) {
                            $groupe->setType($type);
                        }
                    }
                }
                foreach (LegacyValue::rows($groupeArray['typeGroupe']['semestres']) as $semestre) {
                    if (array_key_exists(LegacyValue::key($semestre), $this->tSemestres)) {
                        $groupe->addSemestre($this->tSemestres[LegacyValue::key($semestre)]);
                    }
                }
            }
            $groupe->setOldId($groupeArray['id']);
            $groupe->setKeyEduSign($groupeArray['edusign']);
            $groupe->setParent(null);
            //traiter les semestres

            $this->entityManager->persist($groupe);
            $this->addEnfants($groupeArray, $groupe);
        }

        $this->entityManager->flush();
    }

    /** @param array<array-key, mixed> $groupe */
    private function addEnfants(array $groupe, ?StructureGroupe $structureGroupe): void
    {
        foreach (LegacyValue::rows($groupe['enfants']) as $enfant) {
            $enfantGroupe = new StructureGroupe();
            $enfantGroupe->setLibelle(LegacyValue::string($enfant['libelle']));
            $enfantGroupe->setCodeApogee(substr(LegacyValue::string($enfant['codeApogee']), 0, 25));
            $enfantGroupe->setOrdre(LegacyValue::nullableInt($enfant['ordre']));
            if (array_key_exists('typeGroupe', $enfant) && is_array($enfant['typeGroupe'])) {
                $enfantGroupe->setType(TypeGroupeEnum::TYPE_GROUPE_AUTRE); // Default type
                $type = $enfant['typeGroupe']['type'] ?? null;
                if ($type) {
                    $typeUpper = strtoupper(LegacyValue::string($type));
                    if (str_contains($typeUpper, 'TP')) {
                        $enfantGroupe->setType(TypeGroupeEnum::TYPE_GROUPE_TP);
                    } elseif (str_contains($typeUpper, 'TD')) {
                        $enfantGroupe->setType(TypeGroupeEnum::TYPE_GROUPE_TD);
                    } elseif (str_contains($typeUpper, 'CM')) {
                        $enfantGroupe->setType(TypeGroupeEnum::TYPE_GROUPE_CM);
                    } elseif (defined('TypeGroupeEnum::TYPE_GROUPE_' . $typeUpper)) {
                        $type = constant('TypeGroupeEnum::TYPE_GROUPE_' . $typeUpper);
                        if ($type instanceof TypeGroupeEnum) {
                            $enfantGroupe->setType($type);
                        }
                    }
                }
                foreach (LegacyValue::rows($enfant['typeGroupe']['semestres']) as $semestre) {
                    if (array_key_exists(LegacyValue::key($semestre), $this->tSemestres)) {
                        $enfantGroupe->addSemestre($this->tSemestres[LegacyValue::key($semestre)]);
                    }
                }
            } else {
                $enfantGroupe->setType(TypeGroupeEnum::TYPE_GROUPE_AUTRE);
            }
            $enfantGroupe->setOldId($enfant['id']);
            $enfantGroupe->setKeyEduSign($enfant['edusign']);
            $enfantGroupe->setParent($structureGroupe);
            //traiter les semestres
            foreach ($enfant['typeGroupe']['semestres'] as $semestre) {
                if (array_key_exists($semestre, $this->tSemestres)) {
                    $enfantGroupe->addSemestre($this->tSemestres[$semestre]);
                }
            }
            $this->entityManager->persist($enfantGroupe);
            $this->addEnfants($enfant, $enfantGroupe);
        }
    }
}
