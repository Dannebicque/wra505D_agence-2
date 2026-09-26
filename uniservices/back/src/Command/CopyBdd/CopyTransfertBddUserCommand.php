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
use App\Utils\LooseValue;

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
            $personnel->setNom(LooseValue::string($pers['nom']));
            $personnel->setPrenom(LooseValue::string($pers['prenom']));
            $personnel->setMailUniv(LooseValue::string($pers['mail_univ']));
            $personnel->setUsername(LooseValue::string($pers['username']));
            $personnel->setPassword(LooseValue::nullableString($pers['password']));
            $personnel->setPhotoName(LooseValue::nullableString($pers['photo_name']));
            $personnel->setInitiales(substr(LooseValue::string($pers['initiales']), 0, 3));
            $personnel->setOldId(LooseValue::nullableInt($pers['id']));
            $personnel->setAnneeUniversitaire($this->tAnneeUniversitaire[LooseValue::key($pers['annee_universitaire_id'])]);
            $personnel->setEntreprise(LooseValue::nullableString($pers['entreprise']));
            $personnel->setTelBureau(LooseValue::nullableString($pers['tel_bureau']));
            $personnel->setDomaines(
                // transformer le string $pers['domaines'] en tableau
                explode(',', LooseValue::string($pers['domaines']))
            );
            $personnel->setBureau(LooseValue::nullableString($pers['bureau1']));
            $personnel->setNumeroHarpege(LooseValue::nullableInt($pers['numero_harpege']));
            $personnel->setNbHeuresService(LooseValue::nullableInt($pers['nb_heures_service']));
            $personnel->setMailPerso(LooseValue::nullableString($pers['mail_perso']));
            $personnel->setSitePerso(LooseValue::nullableString($pers['site_perso']));
            $personnel->setSiteUniv(LooseValue::nullableString($pers['site_univ']));
            $personnel->setResponsabilites(LooseValue::nullableString($pers['responsabilites']));
            $personnel->setPosteInterne(LooseValue::nullableString($pers['poste_interne']));
            $personnel->setStatut(StatutEnum::tryFrom(LooseValue::key($pers['statut'])));

            // gestion des adresses
            if ($pers['adresse_id'] !== null && $pers['adresse_id'] !== '') {
                $sql = 'SELECT * FROM adresse WHERE id = ' . LooseValue::castString($pers['adresse_id']);
                $adresse = $this->em->executeQuery($sql)->fetchAssociative();

                $objAdresse = new Adresse(
                    LooseValue::string($adresse['adresse1'] ?? ''),
                    LooseValue::string($adresse['adresse2'] ?? ''),
                    LooseValue::string($adresse['adresse3'] ?? ''),
                    LooseValue::string($adresse['code_postal'] ?? ''),
                    LooseValue::string($adresse['ville'] ?? ''),
                    LooseValue::string($adresse['pays'] ?? 'France')
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

            $this->tPersonnels[LooseValue::key($pers['id'])] = $personnel;

            $this->entityManager->persist($personnel);
            $this->io->info('Personnel : ' . LooseValue::castString($pers['nom']) . ' ajouté pour insertion');
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
            $etudiant->setNom(LooseValue::string($etu['nom']));
            $etudiant->setPrenom(LooseValue::string($etu['prenom']));
            $etudiant->setMailUniv(LooseValue::string($etu['mail_univ']));
            $etudiant->setMailPerso(LooseValue::nullableString($etu['mail_perso']));
            $etudiant->setUsername(LooseValue::string($etu['username']));
            $etudiant->setPhotoName(LooseValue::nullableString($etu['photo_name']));
            $etudiant->setOldId(LooseValue::nullableInt($etu['id']));
            $etudiant->setPassword(LooseValue::nullableString($etu['password']));
            $etudiant->setRoles(LooseValue::strings(json_decode(LooseValue::string($etu['roles']), true) ?? ["ROLE_ETUDIANT"]));
            $etudiant->setSitePerso(LooseValue::nullableString($etu['site_perso']));
            $etudiant->setSiteUniv(LooseValue::nullableString($etu['site_univ']));
            $etudiant->setNumEtudiant(LooseValue::nullableString($etu['num_etudiant']));
            $etudiant->setNumIne(LooseValue::nullableString($etu['num_ine']));
            $etudiant->setAnneeBac(LooseValue::nullableInt($etu['annee_bac']));
            $etudiant->setBoursier(LooseValue::bool($etu['boursier']));
            $etudiant->setAmenagementsParticuliers(LooseValue::nullableString($etu['amenagements_particuliers']));
            $etudiant->setPromotion(LooseValue::nullableInt($etu['promotion']));
            $etudiant->setAnneeSortie(LooseValue::nullableInt($etu['annee_sortie']));
            $etudiant->setDateNaissance(new \DateTime(LooseValue::string($etu['date_naissance'])));
            $etudiant->setTel1(LooseValue::nullableString($etu['tel1']));
            $etudiant->setTel2(LooseValue::nullableString($etu['tel2']));
            $etudiant->setLieuNaissance(LooseValue::nullableString($etu['lieu_naissance']));

            // gestion des adresses : adresse etudiante et adresse parentale
            if ($etu['adresse_id'] !== null && $etu['adresse_id'] !== '') {
                $sql = 'SELECT * FROM adresse WHERE id = ' . LooseValue::castString($etu['adresse_id']);
                $adresse = $this->em->executeQuery($sql)->fetchAssociative();

                $objAdresseEtudiante = new Adresse(
                    LooseValue::string($adresse['adresse1'] ?? ''),
                    LooseValue::string($adresse['adresse2'] ?? ''),
                    LooseValue::string($adresse['adresse3'] ?? ''),
                    LooseValue::string($adresse['ville'] ?? ''),
                    LooseValue::string($adresse['code_postal'] ?? ''),
                    LooseValue::string($adresse['pays'] ?? 'France')
                );
                $etudiant->setAdresseEtudiante($objAdresseEtudiante);
            }

            if ($etu['adresse_parentale_id'] !== null && $etu['adresse_parentale_id'] !== '') {
                $sql = 'SELECT * FROM adresse WHERE id = ' . LooseValue::castString($etu['adresse_parentale_id']);
                $adresse = $this->em->executeQuery($sql)->fetchAssociative();

                $objAdresseParentale = new Adresse(
                    LooseValue::string($adresse['adresse1'] ?? ''),
                    LooseValue::string($adresse['adresse2'] ?? ''),
                    LooseValue::string($adresse['adresse3'] ?? ''),
                    LooseValue::string($adresse['ville'] ?? ''),
                    LooseValue::string($adresse['code_postal'] ?? ''),
                    LooseValue::string($adresse['pays'] ?? 'France')
                );
                $etudiant->setAdresseParentale($objAdresseParentale);
            }

            // Appel à addEtudiantBac
            $this->addEtudiantBac($etudiant, LooseValue::nullableInt($etu['bac_id']));

            $this->entityManager->persist($etudiant);
            $this->io->info('Etudiant : ' . LooseValue::castString($etu['nom']) . ' ajouté pour insertion');

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
            if (!isset($this->tEtudiants[LooseValue::key($etuGroupe['etudiant_id'])])) {
                $this->io->warning('Etudiant ID ' . LooseValue::castString($etuGroupe['etudiant_id']) . ' non trouvé, skip.');
                continue;
            }
            $etudiant = $this->tEtudiants[LooseValue::key($etuGroupe['etudiant_id'])];

            // Chercher le groupe correspondant
            if (!isset($this->tGroupes[LooseValue::key($etuGroupe['groupe_id'])])) {
                $this->io->warning('Groupe ID ' . LooseValue::castString($etuGroupe['groupe_id']) . ' non trouvé, skip.');
                continue;
            }
            $groupe = $this->tGroupes[LooseValue::key($etuGroupe['groupe_id'])];

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
                if ($departement->getOldId() === LooseValue::nullableInt($persDept['departement_id'])) {
                    $departementTrouve = $departement;
                    break;
                }
            }
            // Si le département n'existe pas, on skip
            if (!$departementTrouve) {
                continue;
            }
            $depPers->setDepartement($departementTrouve);
            $depPers->setPersonnel($this->tPersonnels[LooseValue::key($persDept['personnel_id'])]);
            $depPers->setDefaut(LooseValue::bool($persDept['defaut']));
            $depPers->setPermissions(LooseValue::strings(json_decode(LooseValue::string($persDept['roles']), true) ?? []));

            $this->entityManager->persist($depPers);
            // $this->io->info('Personnel : ' . $this->tPersonnels[$persDept['personnel_id']]->getNom() . ' ajouté au département ' . $departementTrouve->getLibelle());
        }

        $this->entityManager->flush();
    }
}
