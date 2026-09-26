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
use App\Utils\LooseValue;

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
            $referentiel->setLibelle(LooseValue::string($ref['libelle']));
            $referentiel->setDescription(LooseValue::nullableString($ref['description']));
            $referentiel->setAnneePublication(LooseValue::castInt($ref['annee_publication']));
            $referentiel->setDepartement($departements[LooseValue::key($ref['departement_id'])]);
            $referentiel->setTypeDiplome(
                $diplomes[LooseValue::key($ref['type_diplome_id'])]
            );

            $this->tReferentiels[LooseValue::key($ref['id'])] = $referentiel;

            $this->entityManager->persist($referentiel);
            $this->io->info('Referentiel ' . LooseValue::castString($ref['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
    public function addParcours(): void
    {
        $sql = "SELECT * FROM apc_parcours";
        $parcours = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($parcours as $par) {
            $parcour = new ApcParcours();
            $parcour->setLibelle(LooseValue::string($par['libelle']));
            $parcour->setOldId(LooseValue::nullableInt($par['id']));
            $parcour->setActif(LooseValue::bool($par['actif']));
            $parcour->setSigle(LooseValue::nullableString($par['code']));
            $parcour->setCouleur(LooseValue::nullableString($par['couleur']));
            $parcour->setOpt(
                [
                    'formation_continue' => LooseValue::bool($par['formation_continue']),
                ]
            );

            $this->tParcours[LooseValue::key($par['id'])] = $parcour;

            $this->entityManager->persist($parcour);
            $this->io->info('Parcours ' . LooseValue::castString($par['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
    public function addCompetences(): void
    {
        $sql = "SELECT * FROM apc_competence";
        $competences = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($competences as $comp) {
            $competence = new ApcCompetence();
            $competence->setOldId(LooseValue::nullableInt($comp['id']));
            $competence->setReferentiel($this->tReferentiels[LooseValue::key($comp['apc_referentiel_id'])]);
            $competence->setLibelle(LooseValue::string($comp['libelle']));
            $competence->setNomCourt(LooseValue::nullableString($comp['nom_court']));
            $competence->setCouleur(LooseValue::nullableString($comp['couleur']));

            // récupérer les composantes essentielles et les ajouter dans le tableau

            $sqlCompEss = "SELECT * FROM apc_composante_essentielle WHERE competence_id = " . LooseValue::castInt($comp['id']);
            $compEss = $this->em->executeQuery($sqlCompEss)->fetchAllAssociative();
            $tCompEss = [];
            foreach ($compEss as $ce) {
                $tCompEss[] = LooseValue::string($ce['libelle']);
            }
            $competence->setComposantesEssentielles($tCompEss);

            // récupérer les situations professionnelles et les ajouter dans le tableau

            $sqlSitPro = "SELECT * FROM apc_situation_professionnelle WHERE competence_id = " . LooseValue::castInt($comp['id']);
            $sitPro = $this->em->executeQuery($sqlSitPro)->fetchAllAssociative();
            $tSitPro = [];
            foreach ($sitPro as $sp) {
                $tSitPro[] = LooseValue::string($sp['libelle']);
            }
            $competence->setSituationsProfessionnelles($tSitPro);

            $this->tCompetences[LooseValue::key($comp['id'])] = $competence;

            $this->entityManager->persist($competence);
            $this->io->info('Competence ' . LooseValue::castString($comp['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
    public function addNiveaux(): void
    {
        $sql = "SELECT * FROM apc_niveau";
        $niveaux = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($niveaux as $niv) {
            $niveau = new ApcNiveau();
            $niveau->setCompetence($this->tCompetences[LooseValue::key($niv['competence_id'])]);
            $niveau->setLibelle(LooseValue::string($niv['libelle']));
            $niveau->setOrdre(LooseValue::int($niv['ordre']));

            // récupérer les parcours associés au niveau pour les ajouter dans la collection
            $sqlNivPar = "SELECT * FROM apc_parcours_niveau WHERE niveau_id = " . LooseValue::castInt($niv['id']);
            $nivPar = $this->em->executeQuery($sqlNivPar)->fetchAllAssociative();
            foreach ($nivPar as $np) {
                $niveau->addParcours($this->tParcours[LooseValue::key($np['parcours_id'])]);
            }

            //todo: récupérer toutes les années sur l'ordre du diplome associé...
            // ajouter toutes les années
            //$niveau->addAnnee();

            $this->tNiveaux[LooseValue::key($niv['id'])] = $niveau;

            $this->entityManager->persist($niveau);
            $this->io->info('Niveau ' . LooseValue::castString($niv['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }

    public function addApprentissageCritique(): void
    {
        $sql = "SELECT * FROM apc_apprentissage_critique";
        $apcs = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($apcs as $ap) {
            $apc = new ApcApprentissageCritique();
            $apc->setNiveau($this->tNiveaux[LooseValue::key($ap['niveau_id'])]);
            $apc->setLibelle(LooseValue::string($ap['libelle']));
            $apc->setCode(LooseValue::nullableString($ap['code']));
            $apc->setOldId(LooseValue::nullableInt($ap['id']));

            $this->entityManager->persist($apc);
            $this->io->info('Apprentissage critique ' . LooseValue::castString($ap['id']) . ' ajouté');
        }

        $this->entityManager->flush();
    }
}
