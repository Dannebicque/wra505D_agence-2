<?php

namespace App\Command\CopyBdd;

use App\Entity\Apc\ApcApprentissageCritique;
use App\Entity\Apc\ApcCompetence;
use App\Entity\Apc\ApcNiveau;
use App\Entity\Apc\ApcParcours;
use App\Entity\Apc\ApcReferentiel;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureTypeDiplome;
use App\Repository\Structure\StructureUeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'copy:transfert-bdd:apc',
    description: 'Add a short description for your command',
)]
class CopyTransfertBddApcCommand extends Command
{
    protected Connection $em;

    protected SymfonyStyle $io;

    /** @var array<int|string, ApcReferentiel> */
    private array $tReferentiels = [];
    /** @var array<int|string, ApcCompetence> */
    private array $tCompetences = [];
    /** @var array<int|string, ApcParcours> */
    private array $tParcours = [];
    /** @var array<int|string, ApcNiveau> */
    private array $tNiveaux = [];

    public function __construct(
        protected EntityManagerInterface $entityManager,
        #[Target('copy')] Connection $copyConnection,
    ) {
        parent::__construct();
        $this->em = $copyConnection;
    }

    protected function configure(): void
    {
    }

    private function effacerTables(): void
    {
        // vider les tables de destination et les réinitialiser
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE apc_referentiel');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE apc_competence');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE apc_niveau');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE apc_parcours');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE apc_niveau_apc_parcours');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE apc_apprentissage_critique');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->effacerTables();

        //APC
        $this->addReferentiel();
        $this->addCompetences();
        $this->addParcours();
        $this->addNiveaux();
        $this->addApprentissageCritique();

        $this->io->success('Processus de recopie terminé.');

        return Command::SUCCESS;
    }

    public function addReferentiel(): void
    {
        $diplomes = array_filter($this->entityManager->getRepository(StructureTypeDiplome::class)->findAllByIdArray(), static fn (mixed $entity): bool => $entity instanceof StructureTypeDiplome);
        $departements = array_filter($this->entityManager->getRepository(StructureDepartement::class)->findAllByIdArray(), static fn (mixed $entity): bool => $entity instanceof StructureDepartement);


        $sql = "SELECT * FROM apc_referentiel";
        $referentiels = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($referentiels as $ref) {
            $referentiel = new ApcReferentiel();
            $referentiel->setLibelle(LegacyValue::string($ref['libelle']));
            $referentiel->setDescription(LegacyValue::nullableString($ref['description']));
            $referentiel->setAnneePublication(LegacyValue::castInt($ref['annee_publication']));
            $referentiel->setDepartement($departements[LegacyValue::key($ref['departement_id'])]);
            $referentiel->setTypeDiplome(
                $diplomes[LegacyValue::key($ref['type_diplome_id'])]
            );

            $this->tReferentiels[LegacyValue::key($ref['id'])] = $referentiel;

            $this->entityManager->persist($referentiel);
            $this->io->info('Referentiel ' . LegacyValue::castString($ref['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
    public function addParcours(): void
    {
        $sql = "SELECT * FROM apc_parcours";
        $parcours = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($parcours as $par) {
            $parcour = new ApcParcours();
            $parcour->setLibelle(LegacyValue::string($par['libelle']));
            $parcour->setOldId(LegacyValue::nullableInt($par['id']));
            $parcour->setActif(LegacyValue::bool($par['actif']));
            $parcour->setSigle(LegacyValue::nullableString($par['code']));
            $parcour->setCouleur(LegacyValue::nullableString($par['couleur']));
            $parcour->setOpt(
                [
                    'formation_continue' => LegacyValue::bool($par['formation_continue']),
                ]
            );

            $this->tParcours[LegacyValue::key($par['id'])] = $parcour;

            $this->entityManager->persist($parcour);
            $this->io->info('Parcours ' . LegacyValue::castString($par['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
    public function addCompetences(): void
    {
        $sql = "SELECT * FROM apc_competence";
        $competences = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($competences as $comp) {
            $competence = new ApcCompetence();
            $competence->setOldId(LegacyValue::nullableInt($comp['id']));
            $competence->setReferentiel($this->tReferentiels[LegacyValue::key($comp['apc_referentiel_id'])]);
            $competence->setLibelle(LegacyValue::string($comp['libelle']));
            $competence->setNomCourt(LegacyValue::nullableString($comp['nom_court']));
            $competence->setCouleur(LegacyValue::nullableString($comp['couleur']));

            // récupérer les composantes essentielles et les ajouter dans le tableau

            $sqlCompEss = "SELECT * FROM apc_composante_essentielle WHERE competence_id = " . LegacyValue::castInt($comp['id']);
            $compEss = $this->em->executeQuery($sqlCompEss)->fetchAllAssociative();
            $tCompEss = [];
            foreach ($compEss as $ce) {
                $tCompEss[] = LegacyValue::string($ce['libelle']);
            }
            $competence->setComposantesEssentielles($tCompEss);

            // récupérer les situations professionnelles et les ajouter dans le tableau

            $sqlSitPro = "SELECT * FROM apc_situation_professionnelle WHERE competence_id = " . LegacyValue::castInt($comp['id']);
            $sitPro = $this->em->executeQuery($sqlSitPro)->fetchAllAssociative();
            $tSitPro = [];
            foreach ($sitPro as $sp) {
                $tSitPro[] = LegacyValue::string($sp['libelle']);
            }
            $competence->setSituationsProfessionnelles($tSitPro);

            $this->tCompetences[LegacyValue::key($comp['id'])] = $competence;

            $this->entityManager->persist($competence);
            $this->io->info('Competence ' . LegacyValue::castString($comp['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
    public function addNiveaux(): void
    {
        $sql = "SELECT * FROM apc_niveau";
        $niveaux = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($niveaux as $niv) {
            $niveau = new ApcNiveau();
            $niveau->setCompetence($this->tCompetences[LegacyValue::key($niv['competence_id'])]);
            $niveau->setLibelle(LegacyValue::string($niv['libelle']));
            $niveau->setOrdre(LegacyValue::int($niv['ordre']));

            // récupérer les parcours associés au niveau pour les ajouter dans la collection
            $sqlNivPar = "SELECT * FROM apc_parcours_niveau WHERE niveau_id = " . LegacyValue::castInt($niv['id']);
            $nivPar = $this->em->executeQuery($sqlNivPar)->fetchAllAssociative();
            foreach ($nivPar as $np) {
                $niveau->addParcours($this->tParcours[LegacyValue::key($np['parcours_id'])]);
            }

            //todo: récupérer toutes les années sur l'ordre du diplome associé...
            // ajouter toutes les années
            //$niveau->addAnnee();

            $this->tNiveaux[LegacyValue::key($niv['id'])] = $niveau;

            $this->entityManager->persist($niveau);
            $this->io->info('Niveau ' . LegacyValue::castString($niv['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }

    public function addApprentissageCritique(): void
    {
        $sql = "SELECT * FROM apc_apprentissage_critique";
        $apcs = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($apcs as $ap) {
            $apc = new ApcApprentissageCritique();
            $apc->setNiveau($this->tNiveaux[LegacyValue::key($ap['niveau_id'])]);
            $apc->setLibelle(LegacyValue::string($ap['libelle']));
            $apc->setCode(LegacyValue::nullableString($ap['code']));
            $apc->setOldId(LegacyValue::nullableInt($ap['id']));

            $this->entityManager->persist($apc);
            $this->io->info('Apprentissage critique ' . LegacyValue::castString($ap['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
}
