<?php

namespace App\Service\OReOF;

use App\Entity\Apc\ApcApprentissageCritique;
use App\Entity\Apc\ApcCompetence;
use App\Entity\Apc\ApcNiveau;
use App\Entity\Apc\ApcReferentiel;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEnseignementUe;
use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Structure\StructureUe;
use App\Enum\TypeEnseignementEnum;
use App\Repository\Apc\ApcCompetenceRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
use App\Repository\Structure\StructureDepartementRepository;
use App\Repository\Structure\StructureDiplomeRepository;
use App\Utils\LooseValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SynchroRefFormation
{
    /** @var array<string, \App\Entity\Apc\ApcCompetence> */
    private array $competencesDips;

    public function __construct(
        protected ApcCompetenceRepository $apcCompetenceRepository,
        protected StructureAnneeUniversitaireRepository $structureAnneeUniversitaireRepository,
        protected StructureDiplomeRepository $structureDiplomeRepository,
        protected HttpClientInterface $httpClient,
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function synchroniser(int $diplomeId, int $idAnneeUniversitaire, int $oreofId): bool
    {
        $anneeUniversitaire = $this->structureAnneeUniversitaireRepository->find($idAnneeUniversitaire);
        $diplome = $this->structureDiplomeRepository->find($diplomeId);


        if (!$anneeUniversitaire || !$diplome) {
            throw new \Exception('Année Universitaire ou diplôme introuvable');
        }

        if ($diplome->getTypeDiplome()?->isApc()) {
            if ($diplome->getParent() !== null) {
                //on pioche dans le diplôme parent
                $diplome = $diplome->getParent();
            }
            $competences = $this->apcCompetenceRepository->findByDiplome($diplome);
            foreach ($competences as $competence) {
                $this->competencesDips[$competence->getNomCourt()] = $competence;
            }
            $this->synchroniserParcours($diplome, $anneeUniversitaire, $oreofId);
        } else {
            //on pioche dans ORéOF
        }

        return true;
    }

    private function synchroniserParcours(StructureDiplome $diplome, StructureAnneeUniversitaire $anneeUniversitaire, int $idOreof): void
    {
        // Récupération des compétences depuis l'API OReOF
        $response = $this->httpClient->request('GET', 'https://oreof.univ-reims.fr/parcours/'.$idOreof.'/maquette/validee_cfvu/export-json', [
            'timeout' => 600,
            'headers' => [
                'Accept' => 'application/json',
                ]
        ]);

        $parcours = json_decode($response->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erreur lors de la décodage de la réponse JSON: ' . json_last_error_msg());
        }
        $semestresOreof = LooseValue::rows(LooseValue::row($parcours)['semestres']);

        // construires les semestres, puis générer les années (pas dans ORéOF), puis attacher les semestres aux années, puis les années au parcours/diplome

        // Ajout d'un PN
        $pn = new StructurePn($diplome);
        $pn->setLibelle($diplome->getSigle(). ' - '.$anneeUniversitaire->getAnnee());
        $pn->setAnneePublication($anneeUniversitaire->getAnnee() ?? 0);
        $pn->setAnneeUniversitaire($anneeUniversitaire);
        $pn->setApcReferentiel($diplome->getReferentiel());
        $this->entityManager->persist($pn);


        // création des années
        $nbAnnees = ceil(count($semestresOreof) / 2);
        $annees = [];
        for ($i = 1; $i <= $nbAnnees; $i++) {
            $annee = new StructureAnnee();
            $pn->addAnnee($annee);
            $annee->setLibelle($diplome->getLibelle(). ' ' . $i);
            $annee->setOrdre($i);
            $annee->setLibelleLong($diplome->getLibelle(). ' année ' . $i);
            $annees[$i] = $annee;
            $this->entityManager->persist($annee);
        }

        $semestres = [];
        foreach ($semestresOreof as $semestre) {
            $sem = new StructureSemestre();
            $ordre = LooseValue::int($semestre['ordre']);

            // lien avec l'année
            $annee = $annees[(int) ceil($ordre / 2)];
            $sem->setAnnee($annee);
            $sem->setLibelle('Semestre ' . $ordre);
            $sem->setOrdreAnnee((int) ceil($ordre / 2));
            $sem->setOrdreLmd($ordre);
            $semestres[] = $sem;
            $ecs = [];
            $this->entityManager->persist($sem);
            foreach (LooseValue::rows($semestre['ues'] ?? []) as $ue) {
                $u = new StructureUe();
                $u->setSemestre($sem);
                $u->setLibelle(LooseValue::string($ue['libelleOrdre']));
                $u->setNumero(LooseValue::int($ue['ordre']));
                $u->setNbEcts(LooseValue::float($ue['ects']));
                $u->setCompetence($this->competencesDips[LooseValue::key($ue['libelle'])] ?? null); //trouver la bonne compétence
                $this->entityManager->persist($u);

                foreach (LooseValue::row($ue['ec'] ?? []) as $e) {
                    if (is_array($e)) {
                        $sigle = LooseValue::key($e['sigle']);
                        if ($e['nature_ec'] === 'SAE' || $e['nature_ec'] === 'Ressource') {
                            //vérifier si EC pas déjà existante
                            if (array_key_exists($sigle, $ecs)) {
                                $ecue = new ScolEnseignementUe($ecs[$sigle], $u);
                                $ecue->setCoefficient(LooseValue::float($e['ects']));
                                $this->entityManager->persist($ecue);
                            } else {
                                $ec = new ScolEnseignement();
                                $ec->setLibelle(LooseValue::string($e['libelle']));
                                $ec->setType($e['nature_ec'] === 'SAE' ? TypeEnseignementEnum::TYPE_SAE : TypeEnseignementEnum::TYPE_RESSOURCE);
                                $ec->setCodeEnseignement(LooseValue::nullableString($e['sigle']));
                                $ec->setDescription(LooseValue::nullableString($e['description']));
                                $ec->setObjectif(LooseValue::nullableString($e['objectifs']));
                                $ec->setLibelleCourt(LooseValue::nullableString($e['sigle']));
                                $ec->setHeures($this->volumesHoraires($e));
                                $ecs[$sigle] = $ec;
                                $ecue = new ScolEnseignementUe($ec, $u);
                                $ecue->setCoefficient(LooseValue::float($e['ects']));
                                $this->entityManager->persist($ecue);
                                $this->entityManager->persist($ec);
                            }
                        } else {
                            $ec = new ScolEnseignement();
                            $ec->setLibelle(LooseValue::string($e['libelle']));
                            $ec->setType(TypeEnseignementEnum::TYPE_MATIERE);
                            $ec->setCodeEnseignement(LooseValue::nullableString($e['sigle']));
                            $ec->setDescription(LooseValue::nullableString($e['description']));
                            $ec->setObjectif(LooseValue::nullableString($e['objectifs']));
                            $ec->setLibelleCourt(LooseValue::nullableString($e['sigle']));
                            $ec->setHeures($this->volumesHoraires($e));
                            $ecue = new ScolEnseignementUe($ec, $u);
                            $this->entityManager->persist($ecue);
                            $this->entityManager->persist($ec);
                        }
                    }

                }
            }
        }


        $this->entityManager->flush();
    }

    /**
     * Volumes présentiels CM, TD et TP d'un EC d'ORéOF, repris à l'identique pour le PN et l'IUT.
     *
     * @param array<mixed> $ec
     *
     * @return array<string, array<string, float|int>>
     */
    private function volumesHoraires(array $ec): array
    {
        $heures = [];
        foreach (['CM', 'TD', 'TP'] as $type) {
            $volume = LooseValue::number(LooseValue::row(LooseValue::row($ec['volumes'])[$type])['presentiel']);
            $heures[$type] = ['PN' => $volume, 'IUT' => $volume];
        }

        return $heures;
    }
}
