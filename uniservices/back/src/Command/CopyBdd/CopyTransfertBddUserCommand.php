<?php

namespace App\Command\CopyBdd;

use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\StatutEnum;
use App\Repository\EtudiantRepository;
use App\Repository\Scolarite\ScolBacRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
use App\Repository\Structure\StructureDepartementRepository;
use App\Repository\Structure\StructureGroupeRepository;
use App\ValueObject\Adresse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'copy:transfert-bdd:user',
    description: 'Add a short description for your command',
)]
class CopyTransfertBddUserCommand extends Command
{
    protected Connection $em;

    /** @var array<int|string, Personnel> */
    protected array $tPersonnels = [];
    /** @var array<int|string, Etudiant> */
    protected array $tEtudiants = [];
    /** @var array<int|string, \App\Entity\Structure\StructureAnneeUniversitaire> */
    protected array $tAnneeUniversitaire = [];
    /** @var array<int|string, \App\Entity\Structure\StructureDepartement> */
    protected array $tDepartements = [];
    /** @var array<int|string, \App\Entity\Structure\StructureGroupe> */
    protected array $tGroupes = [];
    /** @var array<int|string, \App\Entity\Scolarite\ScolBac> */
    protected array $tBacs = [];

    protected SymfonyStyle $io;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        #[Target('copy')] Connection $copyConnection,
        StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository,
        StructureDepartementRepository $structureDepartementRepository,
        StructureGroupeRepository $structureGroupeRepository,
        EtudiantRepository $etudiantRepository,
        ScolBacRepository $scolBacRepository
    ) {
        parent::__construct();
        $this->em = $copyConnection;
        $this->tAnneeUniversitaire = array_filter($structureAnneeUniversitaireRepository->findAllByIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureAnneeUniversitaire);
        $this->tDepartements = array_filter($structureDepartementRepository->findAllByIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureDepartement);
        $this->tGroupes = array_filter($structureGroupeRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureGroupe);
        $this->tEtudiants = array_filter($etudiantRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof Etudiant);
        $this->tBacs = array_filter($scolBacRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Scolarite\ScolBac);
    }

    protected function configure(): void
    {
    }

    private function effacerTables(): void
    {
        // vider les tables de destination et les réinitialiser
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE personnel');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE structure_departement_personnel');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE etudiant');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->effacerTables();

        // Départements
        $this->addPersonnels();
        $this->addEtudiants();
        $this->addPersonnelsDepartements();
        $this->addEtudiantsGroupes();

        $this->io->success('Processus de recopie terminé.');

        return Command::SUCCESS;
    }

    private function addPersonnels(): void
    {
        ini_set('memory_limit', '-1');
        $sql = 'SELECT * FROM personnel';
        $personnels = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($personnels as $pers) {
            $personnel = new Personnel();
            $personnel->setNom(LegacyValue::string($pers['nom']));
            $personnel->setPrenom(LegacyValue::string($pers['prenom']));
            $personnel->setMailUniv(LegacyValue::string($pers['mail_univ']));
            $personnel->setUsername(LegacyValue::string($pers['username']));
            $personnel->setPassword(LegacyValue::nullableString($pers['password']));
            $personnel->setPhotoName(LegacyValue::nullableString($pers['photo_name']));
            $personnel->setInitiales(substr(LegacyValue::string($pers['initiales']), 0, 3));
            $personnel->setOldId(LegacyValue::nullableInt($pers['id']));
            $personnel->setAnneeUniversitaire($this->tAnneeUniversitaire[LegacyValue::key($pers['annee_universitaire_id'])]);
            $personnel->setEntreprise(LegacyValue::nullableString($pers['entreprise']));
            $personnel->setTelBureau(LegacyValue::nullableString($pers['tel_bureau']));
            $personnel->setDomaines(
                // transformer le string $pers['domaines'] en tableau
                explode(',', LegacyValue::string($pers['domaines']))
            );
            $personnel->setBureau(LegacyValue::nullableString($pers['bureau1']));
            $personnel->setNumeroHarpege(LegacyValue::nullableInt($pers['numero_harpege']));
            $personnel->setNbHeuresService(LegacyValue::nullableInt($pers['nb_heures_service']));
            $personnel->setMailPerso(LegacyValue::nullableString($pers['mail_perso']));
            $personnel->setSitePerso(LegacyValue::nullableString($pers['site_perso']));
            $personnel->setSiteUniv(LegacyValue::nullableString($pers['site_univ']));
            $personnel->setResponsabilites(LegacyValue::nullableString($pers['responsabilites']));
            $personnel->setPosteInterne(LegacyValue::nullableString($pers['poste_interne']));
            $personnel->setStatut(StatutEnum::tryFrom(LegacyValue::key($pers['statut'])));

            // gestion des adresses
            if ($pers['adresse_id'] !== null && $pers['adresse_id'] !== '') {
                $sql = 'SELECT * FROM adresse WHERE id = ' . LegacyValue::castString($pers['adresse_id']);
                $adresse = $this->em->executeQuery($sql)->fetchAssociative();

                $objAdresse = new Adresse(
                    LegacyValue::string($adresse['adresse1'] ?? ''),
                    LegacyValue::string($adresse['adresse2'] ?? ''),
                    LegacyValue::string($adresse['adresse3'] ?? ''),
                    LegacyValue::string($adresse['code_postal'] ?? ''),
                    LegacyValue::string($adresse['ville'] ?? ''),
                    LegacyValue::string($adresse['pays'] ?? 'France')
                );
                $personnel->setAdressePersonnelle($objAdresse);
            }

            /*
             * "id" => 1
  "statut" => "vacataire"
  "poste_interne" => null
  "tel_bureau" => null
  "responsabilites" => null
  "domaines" => null
  "entreprise" => null
  "bureau1" => null
  "bureau2" => null
  "numero_harpege" => 18027
  "initiales" => null
  "cv_name" => ""
  "nb_heures_service" => 384.0
  "deleted" => 0
  "couleur" => ""
  "slug" => "a.martinot"
  "type_user" => "vacataire"
  "site_univ" => null
  "mail_perso" => "a.martinot@wanadoo.fr"
  "site_perso" => null
  "civilite" => "M."
  "date_naissance" => "1956-06-17"
  "tel1" => null
  "tel2" => null
  "remarque" => null
  "signature" => null
  "visible" => 1
  "updated" => "2024-09-19 17:27:26"
  "reset_token" => null
  "signature_electronique" => null
  "lieu_naissance" => "Chaumont"
  "configuration" => null
  "access_originaux" => 0
  "id_edu_sign" => "{"3":"xwdv9d59utg5fafu"}"
             */

            $this->tPersonnels[LegacyValue::key($pers['id'])] = $personnel;

            $this->entityManager->persist($personnel);
            $this->io->info('Personnel : ' . LegacyValue::castString($pers['nom']) . ' ajouté pour insertion');
        }

        $this->entityManager->flush();
    }

    private function addEtudiants(): void
    {
        ini_set('memory_limit', '-1');

        $sql = 'SELECT * FROM etudiant WHERE semestre_id IS NOT NULL and annee_sortie = 0'; // juste pour des datas
        $etudiants = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($etudiants as $etu) {
            $etudiant = new Etudiant();
            $etudiant->setNom(LegacyValue::string($etu['nom']));
            $etudiant->setPrenom(LegacyValue::string($etu['prenom']));
            $etudiant->setMailUniv(LegacyValue::string($etu['mail_univ']));
            $etudiant->setMailPerso(LegacyValue::nullableString($etu['mail_perso']));
            $etudiant->setUsername(LegacyValue::string($etu['username']));
            $etudiant->setPhotoName(LegacyValue::nullableString($etu['photo_name']));
            $etudiant->setOldId(LegacyValue::nullableInt($etu['id']));
            $etudiant->setPassword(LegacyValue::nullableString($etu['password']));
            $etudiant->setRoles(LegacyValue::strings(json_decode(LegacyValue::string($etu['roles']), true) ?? ["ROLE_ETUDIANT"]));
            $etudiant->setSitePerso(LegacyValue::nullableString($etu['site_perso']));
            $etudiant->setSiteUniv(LegacyValue::nullableString($etu['site_univ']));
            $etudiant->setNumEtudiant(LegacyValue::nullableString($etu['num_etudiant']));
            $etudiant->setNumIne(LegacyValue::nullableString($etu['num_ine']));
            $etudiant->setAnneeBac(LegacyValue::nullableInt($etu['annee_bac']));
            $etudiant->setBoursier(LegacyValue::bool($etu['boursier']));
            $etudiant->setAmenagementsParticuliers(LegacyValue::nullableString($etu['amenagements_particuliers']));
            $etudiant->setPromotion(LegacyValue::nullableInt($etu['promotion']));
            $etudiant->setAnneeSortie(LegacyValue::nullableInt($etu['annee_sortie']));
            $etudiant->setDateNaissance(new \DateTime(LegacyValue::string($etu['date_naissance'])));
            $etudiant->setTel1(LegacyValue::nullableString($etu['tel1']));
            $etudiant->setTel2(LegacyValue::nullableString($etu['tel2']));
            $etudiant->setLieuNaissance(LegacyValue::nullableString($etu['lieu_naissance']));

            // gestion des adresses : adresse etudiante et adresse parentale
            if ($etu['adresse_id'] !== null && $etu['adresse_id'] !== '') {
                $sql = 'SELECT * FROM adresse WHERE id = ' . LegacyValue::castString($etu['adresse_id']);
                $adresse = $this->em->executeQuery($sql)->fetchAssociative();

                $objAdresseEtudiante = new Adresse(
                    LegacyValue::string($adresse['adresse1'] ?? ''),
                    LegacyValue::string($adresse['adresse2'] ?? ''),
                    LegacyValue::string($adresse['adresse3'] ?? ''),
                    LegacyValue::string($adresse['ville'] ?? ''),
                    LegacyValue::string($adresse['code_postal'] ?? ''),
                    LegacyValue::string($adresse['pays'] ?? 'France')
                );
                $etudiant->setAdresseEtudiante($objAdresseEtudiante);
            }

            if ($etu['adresse_parentale_id'] !== null && $etu['adresse_parentale_id'] !== '') {
                $sql = 'SELECT * FROM adresse WHERE id = ' . LegacyValue::castString($etu['adresse_parentale_id']);
                $adresse = $this->em->executeQuery($sql)->fetchAssociative();

                $objAdresseParentale = new Adresse(
                    LegacyValue::string($adresse['adresse1'] ?? ''),
                    LegacyValue::string($adresse['adresse2'] ?? ''),
                    LegacyValue::string($adresse['adresse3'] ?? ''),
                    LegacyValue::string($adresse['ville'] ?? ''),
                    LegacyValue::string($adresse['code_postal'] ?? ''),
                    LegacyValue::string($adresse['pays'] ?? 'France')
                );
                $etudiant->setAdresseParentale($objAdresseParentale);
            }

            // Appel à addEtudiantBac
            $this->addEtudiantBac($etudiant, LegacyValue::nullableInt($etu['bac_id']));

            $this->entityManager->persist($etudiant);
            $this->io->info('Etudiant : ' . LegacyValue::castString($etu['nom']) . ' ajouté pour insertion');

            /*
             * "id" => 30
  "semestre_id" => null
  "bac_id" => null
  "uuid" => "�G�Lo�C�"
  "num_etudiant" => "21701820"
  "num_ine" => "2410019804U"
  "annee_bac" => 2017
  "boursier" => 0
  "demandeur_emploi" => 0
  "deleted" => 0
  "amenagements_particuliers" => null
  "promotion" => 2019
  "intitule_securite_sociale" => null
  "adresse_securite_sociale" => null
  "annee_sortie" => 2020
  "slug" => "julie.bastard"
  "type_user" => "etudiant"
  "site_univ" => null
  "mail_perso" => null
  "site_perso" => null
  "civilite" => "Mme"
  "date_naissance" => "1999-09-13"
  "tel1" => "06.46.29.77.06"
  "tel2" => null
  "remarque" => null
  "signature" => null
  "visible" => 1
  "updated" => "2021-06-29 10:16:48"
  "reset_token" => null
  "departement_id" => 1
  "login_specifique" => null
  "formation_continue" => 0
  "lieu_naissance" => null
  "id_edu_sign" => null

             */
        }

        $this->entityManager->flush();
    }

    private function addEtudiantsGroupes(): void
    {
        $this->tEtudiants = array_filter($this->entityManager->getRepository(Etudiant::class)->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof Etudiant);
        $sql = 'SELECT * FROM etudiant_groupe';
        $etudiantsGroupes = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($etudiantsGroupes as $etuGroupe) {
            // Chercher l'étudiant correspondant
            if (!isset($this->tEtudiants[LegacyValue::key($etuGroupe['etudiant_id'])])) {
                $this->io->warning('Etudiant ID ' . LegacyValue::castString($etuGroupe['etudiant_id']) . ' non trouvé, skip.');
                continue;
            }
            $etudiant = $this->tEtudiants[LegacyValue::key($etuGroupe['etudiant_id'])];

            // Chercher le groupe correspondant
            if (!isset($this->tGroupes[LegacyValue::key($etuGroupe['groupe_id'])])) {
                $this->io->warning('Groupe ID ' . LegacyValue::castString($etuGroupe['groupe_id']) . ' non trouvé, skip.');
                continue;
            }
            $groupe = $this->tGroupes[LegacyValue::key($etuGroupe['groupe_id'])];

            // Ajouter l'étudiant au groupe
            $groupe->addEtudiant($etudiant);
            $etudiant->addGroupe($groupe);

            $this->entityManager->persist($groupe);
            $this->entityManager->persist($etudiant);
            $this->io->info('Etudiant : ' . $etudiant->getNom() . ' ajouté au groupe ' . $groupe->getLibelle());
        }
    }

    private function addEtudiantBac(Etudiant $etudiant, ?int $bacId): void
    {
        if ($bacId !== null) {
            if (!isset($this->tBacs[$bacId])) {
                $this->io->warning('Bac ID ' . $bacId . ' non trouvé, skip.');
                return;
            }
            $bac = $this->tBacs[$bacId];
            $etudiant->setBac($bac);
            $this->entityManager->persist($etudiant);
            $this->io->info('Etudiant : ' . $etudiant->getNom() . ' associé au bac ' . $bac->getLibelle());
        }
    }

    private function addPersonnelsDepartements(): void
    {
        $sql = 'SELECT * FROM personnel_departement';
        $persDepts = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($persDepts as $persDept) {
            $depPers = new StructureDepartementPersonnel();
            // Chercher le département correspondant
            $departementTrouve = null;
            foreach ($this->tDepartements as $departement) {
                if ($departement->getOldId() === LegacyValue::nullableInt($persDept['departement_id'])) {
                    $departementTrouve = $departement;
                    break;
                }
            }
            // Si le département n'existe pas, on skip
            if (!$departementTrouve) {
                continue;
            }
            $depPers->setDepartement($departementTrouve);
            $depPers->setPersonnel($this->tPersonnels[LegacyValue::key($persDept['personnel_id'])]);
            $depPers->setDefaut(LegacyValue::bool($persDept['defaut']));
            $depPers->setPermissions(LegacyValue::strings(json_decode(LegacyValue::string($persDept['roles']), true) ?? []));

            $this->entityManager->persist($depPers);
            // $this->io->info('Personnel : ' . $this->tPersonnels[$persDept['personnel_id']]->getNom() . ' ajouté au département ' . $departementTrouve->getLibelle());
        }

        $this->entityManager->flush();
    }
}
