<?php

namespace App\Command\CopyBdd;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEnseignementUe;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Enum\TypeEnseignementEnum;
use App\Repository\Apc\ApcApprentissageCritiqueRepository;
use App\Repository\Apc\ApcCompetenceRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
use App\Repository\Structure\StructureUeRepository;
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
    name: 'copy:transfert-bdd:enseignements',
    description: 'Copie les matières, ressources, SAE',
)]
class CopyTransfertBddEnseignementsCommand extends Command
{
    protected Connection $em;

    /** @var array<int|string, ScolEnseignement> */
    protected array $tMatieres = [];
    /** @var array<int|string, \App\Entity\Apc\ApcCompetence> */
    protected array $tCompetences = [];
    /** @var array<int|string, \App\Entity\Structure\StructureUe> */
    protected array $tUes = [];
    /** @var array<int|string, \App\Entity\Apc\ApcApprentissageCritique> */
    protected array $tApprentissages = [];
    protected ?StructureAnneeUniversitaire $anneeUniv = null;

    protected SymfonyStyle $io;
    protected string $base_url;


    public function __construct(
        protected EntityManagerInterface   $entityManager,
        #[Target('copy')] Connection $copyConnection,
        ApcApprentissageCritiqueRepository $apcApprentissageCritiqueRepository,
        ApcCompetenceRepository            $apcCompetenceRepository,
        StructureUeRepository              $structureUeRepository,
        StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository,
        protected HttpClientInterface      $httpClient,
        ParameterBagInterface              $params
    ) {
        parent::__construct();
        $this->tCompetences = array_filter($apcCompetenceRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Apc\ApcCompetence);
        $this->tApprentissages = array_filter($apcApprentissageCritiqueRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Apc\ApcApprentissageCritique);
        $this->tUes = array_filter($structureUeRepository->findAllByOldIdArray(), static fn (mixed $entity): bool => $entity instanceof \App\Entity\Structure\StructureUe);
        $this->anneeUniv = $structureAnneeUniversitaireRepository->findOneBy(['actif' => true,]);
        $this->base_url = $params->get('URL_INTRANET_V3');
        $this->httpClient = HttpClient::create([
            'verify_peer' => false,
            'verify_host' => false,
        ]);
        $this->em = $copyConnection;
    }

    protected function configure(): void
    {
    }

    private function effacerTables(): void
    {
        // vider les tables de destination et les réinitialiser
        $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE scol_enseignement');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE scol_enseignement_ue');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE scol_enseignement_apc_apprentissage_critique');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE scol_evaluation');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE etudiant_note');
        $this->entityManager->getConnection()->executeStatement('SET
FOREIGN_KEY_CHECKS=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->effacerTables();

        // Départements
        $this->addMatieres();
        $this->addRessources();
        $this->addSaes();

        $this->io->success('Processus de recopie terminé.');

        return Command::SUCCESS;
    }

    private function addMatieres(): void
    {
        // matières, ressources, SAE
        $sql = 'SELECT * FROM matiere WHERE matiere_parent_id IS NULL';
        $matieres = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($matieres as $mat) {
            if (array_key_exists(LooseValue::key($mat['ue_id']), $this->tUes)) {
                $matiere = new ScolEnseignement();
                $matiere->setLibelle(LooseValue::string($mat['libelle']));
                $matiere->setCodeEnseignement(LooseValue::nullableString($mat['code_matiere']));
                $matiere->setCodeApogee(LooseValue::nullableString($mat['code_element']));
                $matiere->setHeures([
                    'CM' => ['PN' => LooseValue::float($mat['cm_ppn']), 'IUT' => LooseValue::float($mat['cm_formation'])],
                    'TD' => ['PN' => LooseValue::float($mat['td_ppn']), 'IUT' => LooseValue::float($mat['td_formation'])],
                    'TP' => ['PN' => LooseValue::float($mat['tp_ppn']), 'IUT' => LooseValue::float($mat['tp_formation'])],
                    'Projet' => ['PN' => 0, 'IUT' => 0],
                ]);
                $matiere->setType(TypeEnseignementEnum::TYPE_MATIERE);
                $matiere->setBonification(LooseValue::bool($mat['pac']));
                $matiere->setDescription(LooseValue::nullableString($mat['description']));
                $matiere->setNbNotes(LooseValue::castInt($mat['nb_notes']));
                $matiere->setLibelleCourt(LooseValue::nullableString($mat['libelle_court']));
                $matiere->setSuspendu(LooseValue::bool($mat['suspendu']));
                $matiere->setMutualisee(LooseValue::bool($mat['mutualisee']));
                $matiere->setMotsCles(LooseValue::nullableString($mat['mots_cles']));
                $matiere->setObjectif(LooseValue::nullableString($mat['objectifs_module']));
                $matiere->setPrerequis(LooseValue::nullableString($mat['pre_requis']));
                $matiere->setOldId(LooseValue::nullableInt($mat['id']));

                $nbNotes = LooseValue::castInt($mat['nb_notes']);
                for ($i = 1; $i <= $nbNotes; $i++) {
                    $evaluation = new ScolEvaluation();
                    $evaluation->setLibelle('Évaluation ' . $i);
                    $evaluation->setEnseignement($matiere);
                    $evaluation->setVisible(false);
                    $evaluation->setModifiable(false);
                    $evaluation->setUuid(new UuidV4());
                    $evaluation->setAnneeUniversitaire($this->anneeUniv);
                    $this->entityManager->persist($evaluation);
                }

                /*
                 * array:30 [
      "ppn_id" => 1
      "parcours_id" => null
    ]
                 */
                $this->entityManager->persist($matiere);
                $this->tMatieres[LooseValue::key($mat['id'])] = $matiere;

                if (LooseValue::string($mat['ue_id']) !== '') {

                    $matiereUe = new ScolEnseignementUe(
                        $matiere,
                        $this->tUes[LooseValue::key($mat['ue_id'])],
                    );
                    $matiereUe->setCoefficient(LooseValue::float($mat['coefficient']));
                    $matiereUe->setEcts(LooseValue::float($mat['nb_ects']));
                    $this->entityManager->persist($matiereUe);

                }

                $this->io->info('Matière : ' . LooseValue::castString($mat['libelle']) . ' ajouté pour insertion');
            }
        }

        $sql = 'SELECT * FROM matiere WHERE matiere_parent_id IS NOT NULL';
        $matieres = $this->em->executeQuery($sql)->fetchAllAssociative();

        foreach ($matieres as $mat) {
            if (array_key_exists(LooseValue::key($mat['ue_id']), $this->tUes)) {
                $matiere = new ScolEnseignement();
                $matiere->setLibelle(LooseValue::string($mat['libelle']));
                $matiere->setCodeEnseignement(LooseValue::nullableString($mat['code_matiere']));
                $matiere->setCodeApogee(LooseValue::nullableString($mat['code_element']));
                $matiere->setHeures([
                    'CM' => ['PN' => LooseValue::float($mat['cm_ppn']), 'IUT' => LooseValue::float($mat['cm_formation'])],
                    'TD' => ['PN' => LooseValue::float($mat['td_ppn']), 'IUT' => LooseValue::float($mat['td_formation'])],
                    'TP' => ['PN' => LooseValue::float($mat['tp_ppn']), 'IUT' => LooseValue::float($mat['tp_formation'])],
                    'Projet' => ['PN' => 0, 'IUT' => 0],
                ]);
                $matiere->setType(TypeEnseignementEnum::TYPE_MATIERE);
                $matiere->setBonification(LooseValue::bool($mat['pac']));
                $matiere->setDescription(LooseValue::nullableString($mat['description']));
                $matiere->setNbNotes(LooseValue::castInt($mat['nb_notes']));
                $matiere->setLibelleCourt(LooseValue::nullableString($mat['libelle_court']));
                $matiere->setSuspendu(LooseValue::bool($mat['suspendu']));
                $matiere->setMutualisee(LooseValue::bool($mat['mutualisee']));
                $matiere->setMotsCles(LooseValue::nullableString($mat['mots_cles']));
                $matiere->setObjectif(LooseValue::nullableString($mat['objectifs_module']));
                $matiere->setPrerequis(LooseValue::nullableString($mat['pre_requis']));
                $matiere->setParent($this->tMatieres[LooseValue::key($mat['matiere_parent_id'])]);
                $matiere->setOldId(LooseValue::nullableInt($mat['id']));

                /*
                 * array:30 [
      "ppn_id" => 1
      "parcours_id" => null
    ]
                 */
                $this->entityManager->persist($matiere);

                if (LooseValue::string($mat['ue_id']) !== '') {
                    $matiereUe = new ScolEnseignementUe(
                        $matiere,
                        $this->tUes[LooseValue::key($mat['ue_id'])],
                    );
                    $matiereUe->setCoefficient(LooseValue::float($mat['coefficient']));
                    $matiereUe->setEcts(LooseValue::float($mat['nb_ects']));
                    $this->entityManager->persist($matiereUe);

                }

                $this->io->info('Matière : ' . LooseValue::castString($mat['libelle']) . ' ajouté pour insertion');
            }
        }

        $this->entityManager->flush();
    }

    private function addRessources(): int
    {
        $response = $this->httpClient->request('GET', $this->base_url . '/ressources', [
            'timeout' => 600,
        ]);

        $matieres = json_decode($response->getContent(), true);
        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->io->error('JSON decoding error: ' . json_last_error_msg());
            return Command::FAILURE;
        }
        $matieres = LooseValue::rows($matieres);

        foreach ($matieres as $mat) {
            $matiere = new ScolEnseignement();
            $matiere->setLibelle(LooseValue::string($mat['libelle']));
            $matiere->setCodeEnseignement(LooseValue::nullableString($mat['code_matiere']));
            $matiere->setCodeApogee(LooseValue::nullableString($mat['code_element']));
            $matiere->setHeures([
                'CM' => ['PN' => LooseValue::float($mat['cm_ppn']), 'IUT' => LooseValue::float($mat['cm_formation'])],
                'TD' => ['PN' => LooseValue::float($mat['td_ppn']), 'IUT' => LooseValue::float($mat['td_formation'])],
                'TP' => ['PN' => LooseValue::float($mat['tp_ppn']), 'IUT' => LooseValue::float($mat['tp_formation'])],
                'Projet' => ['PN' => 0, 'IUT' => 0],
            ]);
            $matiere->setType(TypeEnseignementEnum::TYPE_RESSOURCE);
            $matiere->setBonification(false);
            $matiere->setDescription(LooseValue::nullableString($mat['description']));
            $matiere->setNbNotes(LooseValue::castInt($mat['nb_notes']));
            $matiere->setLibelleCourt(LooseValue::nullableString($mat['libelle_court']));
            $matiere->setSuspendu(LooseValue::bool($mat['suspendu']));
            $matiere->setMutualisee(LooseValue::bool($mat['mutualisee']));
            $matiere->setMotsCles(LooseValue::nullableString($mat['mots_cles']));
            $matiere->setPrerequis(LooseValue::nullableString($mat['pre_requis']));
            $matiere->setOldId(LooseValue::nullableInt($mat['id']));

            $nbNotes = LooseValue::castInt($mat['nb_notes']);
            for ($i = 1; $i <= $nbNotes; $i++) {
                $evaluation = new ScolEvaluation();
                $evaluation->setLibelle('Évaluation ' . $i);
                $evaluation->setEnseignement($matiere);
                $evaluation->setVisible(false);
                $evaluation->setModifiable(false);
                $evaluation->setUuid(new UuidV4());
                $evaluation->setAnneeUniversitaire($this->anneeUniv);
                $this->entityManager->persist($evaluation);
            }

            /*
  "id" => 1
  "semestre_id" => Via les UE ??
  "commentaire" => null
  "ressource_parent" => 0
  "has_coefficient_different" => 0
             */
            $this->entityManager->persist($matiere);
            //todo: traiter les ressources enfants a ajouter dans l'API
            // $this->tMatieres[$mat['id']] = $matiere;
            // dump($mat['id']);
            // récupérer les dépendances de ApcRessources : ApprentissagesCrtiques, Competences, semestre
            $taddUes = [];
            if (array_key_exists('ues', $mat)) {
                foreach ($mat['ues'] as $apcCompetence) {
                    //                    dd($apcCompetence);
                    if (array_key_exists(LooseValue::key($apcCompetence['ue_id']), $this->tUes) &&
                        !array_key_exists(LooseValue::key($this->tUes[LooseValue::key($apcCompetence['ue_id'])]->getId()), $taddUes)
                    ) {
                        dump($apcCompetence['ue_id']);
                        $apc = new ScolEnseignementUe(
                            $matiere,
                            $this->tUes[LooseValue::key($apcCompetence['ue_id'])],
                        );
                        $taddUes[LooseValue::key($this->tUes[LooseValue::key($apcCompetence['ue_id'])]->getId())] = $apc;
                        $apc->setCoefficient((float)$apcCompetence['coefficient']);
                        $apc->setEcts((float)$apcCompetence['coefficient']);
                        //todo: parcours
                        $this->entityManager->persist($apc);
                    } else {
                        $this->io->error('UE ' . $apcCompetence['ue_id'] . ' non trouvée dans la table des UEs');
                    }
                }
            }

            $sqlApcCritique = 'SELECT * FROM apc_ressource_apprentissage_critique WHERE ressource_id = ' . LooseValue::castInt($mat['id']);
            $apcCritiques = $this->em->executeQuery($sqlApcCritique)->fetchAllAssociative();

            foreach ($apcCritiques as $apcCritique) {
                if (array_key_exists(LooseValue::key($apcCritique['apprentissage_critique_id']), $this->tApprentissages) &&
                    !$matiere->getApprentissageCritique()->contains($this->tApprentissages[LooseValue::key($apcCritique['apprentissage_critique_id'])])
                ) {
                    $matiere->addApprentissageCritique($this->tApprentissages[LooseValue::key($apcCritique['apprentissage_critique_id'])]);
                }
            }

            $this->io->info('Ressource : ' . $mat['libelle'] . ' ajouté pour insertion');
        }

        //        $sql = 'SELECT * FROM apc_ressource WHERE ressource_parent = true';
        //        $matieres = $this->em->executeQuery($sql)->fetchAllAssociative();
        //
        //        foreach ($matieres as $mat) {
        //            $matiere = new ScolEnseignement();
        //            $matiere->setLibelle($mat['libelle']);
        //            $matiere->setCodeEnseignement($mat['code_matiere']);
        //            $matiere->setCodeApogee($mat['code_element']);
        //            $matiere->setHeures([
        //                'heures' => [
        //                    'CM' => ['PN' => (float)$mat['cm_ppn'], 'IUT' => (float)$mat['cm_formation']],
        //                    'TD' => ['PN' => (float)$mat['td_ppn'], 'IUT' => (float)$mat['td_formation']],
        //                    'TP' => ['PN' => (float)$mat['tp_ppn'], 'IUT' => (float)$mat['tp_formation']],
        //                    'Projet' => ['PN' => 0, 'IUT' => 0],
        //                ],
        //            ]);
        //            $matiere->setType(TypeEnseignementEnum::TYPE_MATIERE);
        //            $matiere->setBonification((bool)$mat['pac']);
        //            $matiere->setDescription($mat['description']);
        //            $matiere->setNbNotes((int)$mat['nb_notes']);
        //            $matiere->setLibelleCourt($mat['libelle_court']);
        //            $matiere->setSuspendu((bool)$mat['suspendu']);
        //            $matiere->setMutualisee((bool)$mat['mutualisee']);
        //            $matiere->setMotsCles($mat['mots_cles']);
        //            $matiere->setObjectif($mat['objectifs_module']);
        //            $matiere->setPrerequis($mat['pre_requis']);
        //            $matiere->setParent($this->tMatieres[$mat['matiere_parent_id']]);
        //
        //            /*
        //             * array:30 [
        //  "ppn_id" => 1
        //  "parcours_id" => null
        //]
        //             */
        //            $this->entityManager->persist($matiere);
        //
        //            if ($mat['ue_id'] !== '') {
        //
        //                $matiereUe = new ScolEnseignementUe(
        //                    $matiere,
        //                    $this->tUes[$mat['ue_id']],
        //                );
        //                $matiereUe->setCoefficient((float)$mat['coefficient']);
        //                $matiereUe->setEcts((float)$mat['nb_ects']);
        //                $this->entityManager->persist($matiereUe);
        //
        //            }
        //
        //            $sqlApcCritique = 'SELECT * FROM apc_ressource_apprentissage_critique WHERE ressource_id = ' . $mat['id'];
        //            $apcCritiques = $this->em->executeQuery($sqlApcCritique)->fetchAllAssociative();
        //
        //            foreach ($apcCritiques as $apcCritique) {
        //                if (array_key_exists($apcCritique['apprentissage_critique_id'], $this->tApprentissages) &&
        //                    !$matiere->getApcApprentissageCritique()->contains($this->tApprentissages[$apcCritique['apprentissage_critique_id']])
        //                ) {
        //                    $matiere->addApcApprentissageCritique($this->tApprentissages[$apcCritique['apprentissage_critique_id']]);
        //                }
        //            }
        //
        //            $this->io->info('Ressource enfant : ' . $mat['libelle'] . ' ajouté pour insertion');
        //        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function addSaes(): void
    {
        $response = $this->httpClient->request('GET', $this->base_url . '/saes');
        // Un JSON invalide ne copiait rien : null est parcouru comme une liste vide.
        $matieres = LooseValue::rows(json_decode($response->getContent(), true) ?? []);
        // matières, ressources, SAE

        foreach ($matieres as $mat) {
            $matiere = new ScolEnseignement();
            $matiere->setLibelle(LooseValue::string($mat['libelle']));
            $matiere->setCodeEnseignement(LooseValue::nullableString($mat['code_matiere']));
            $matiere->setCodeApogee(LooseValue::nullableString($mat['code_element']));
            $matiere->setHeures([
                'CM' => ['PN' => LooseValue::float($mat['cm_ppn']), 'IUT' => LooseValue::float($mat['cm_formation'])],
                'TD' => ['PN' => LooseValue::float($mat['td_ppn']), 'IUT' => LooseValue::float($mat['td_formation'])],
                'TP' => ['PN' => LooseValue::float($mat['tp_ppn']), 'IUT' => LooseValue::float($mat['tp_formation'])],
                'Projet' => ['PN' => LooseValue::float($mat['projet_ppn']), 'IUT' => LooseValue::float($mat['projet_formation'])],
            ]);
            $matiere->setType(TypeEnseignementEnum::TYPE_SAE);
            $matiere->setBonification(false);
            $matiere->setDescription(LooseValue::nullableString($mat['description']));
            $matiere->setNbNotes(LooseValue::castInt($mat['nb_notes']));
            $matiere->setLibelleCourt(LooseValue::nullableString($mat['libelle_court']));
            $matiere->setSuspendu(LooseValue::bool($mat['suspendu']));
            $matiere->setMutualisee(LooseValue::bool($mat['mutualisee']));
            $matiere->setExemple(LooseValue::nullableString($mat['exemple']));
            $matiere->setLivrables(LooseValue::nullableString($mat['livrables']));
            $matiere->setOldId(LooseValue::nullableInt($mat['id']));

            $nbNotes = LooseValue::castInt($mat['nb_notes']);
            for ($i = 1; $i <= $nbNotes; $i++) {
                $evaluation = new ScolEvaluation();
                $evaluation->setLibelle('Évaluation ' . $i);
                $evaluation->setEnseignement($matiere);
                $evaluation->setVisible(false);
                $evaluation->setModifiable(false);
                $evaluation->setUuid(new UuidV4());
                //todo: corriger pour récupérer la bonne année universitaire de l'éval depuis l'enseignement
                $evaluation->setAnneeUniversitaire($this->anneeUniv);
                $this->entityManager->persist($evaluation);
            }

            /*
??
             */
            $this->entityManager->persist($matiere);
            //  $this->tMatieres[$mat['id']] = $matiere;

            // récupérer les dépendances de ApcRessources : ApprentissagesCrtiques, Competences, semestre
            $taddUes = [];
            if (array_key_exists('ues', $mat)) {
                foreach ($mat['ues'] as $apcCompetence) {
                    //dd($apcCompetence);
                    if (array_key_exists(LooseValue::key($apcCompetence['ue_id']), $this->tUes) &&
                        !array_key_exists(LooseValue::key($this->tUes[LooseValue::key($apcCompetence['ue_id'])]->getId()), $taddUes)
                    ) {
                        dump($apcCompetence['ue_id']);
                        $apc = new ScolEnseignementUe(
                            $matiere,
                            $this->tUes[LooseValue::key($apcCompetence['ue_id'])],
                        );
                        $taddUes[$this->tUes[LooseValue::key($apcCompetence['ue_id'])]->getId()] = $apc;
                        $apc->setCoefficient((float)$apcCompetence['coefficient']);
                        $apc->setEcts((float)$apcCompetence['coefficient']);
                        //todo: parcours
                        $this->entityManager->persist($apc);
                    }
                }
            }

            $sqlApcCritique = 'SELECT * FROM apc_sae_apprentissage_critique WHERE sae_id = ' . LooseValue::castInt($mat['id']);
            $apcCritiques = $this->em->executeQuery($sqlApcCritique)->fetchAllAssociative();


            foreach ($apcCritiques as $apcCritique) {
                if (array_key_exists(LooseValue::key($apcCritique['apprentissage_critique_id']), $this->tApprentissages) &&
                    !$matiere->getApprentissageCritique()->contains($this->tApprentissages[LooseValue::key($apcCritique['apprentissage_critique_id'])])
                ) {
                    $matiere->addApprentissageCritique($this->tApprentissages[LooseValue::key($apcCritique['apprentissage_critique_id'])]);
                }
            }

            $this->io->info('SAE : ' . $mat['libelle'] . ' ajouté pour insertion');
        }

        $this->entityManager->flush();
    }
}
