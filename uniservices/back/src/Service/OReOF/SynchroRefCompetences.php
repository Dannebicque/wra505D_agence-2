<?php

namespace App\Service\OReOF;

use App\Entity\Apc\ApcApprentissageCritique;
use App\Entity\Apc\ApcCompetence;
use App\Entity\Apc\ApcNiveau;
use App\Entity\Apc\ApcReferentiel;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDiplome;
use App\Repository\Structure\StructureDepartementRepository;
use App\Repository\Structure\StructureDiplomeRepository;
use App\Utils\LooseValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SynchroRefCompetences
{
    public function __construct(
        protected StructureDepartementRepository $structureDepartementRepository,
        protected StructureDiplomeRepository $structureDiplomeRepository,
        protected HttpClientInterface $httpClient,
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function synchroniser(int $departementId, int $diplomeId): bool
    {
        $departement = $this->structureDepartementRepository->find($departementId);
        $diplome = $this->structureDiplomeRepository->find($diplomeId);

        if (!$departement || !$diplome) {
            throw new \Exception('Département ou diplôme introuvable');
        }

        if ($diplome->getTypeDiplome()?->isApc()) {
            if ($diplome->getParent() !== null) {
                //on pioche dans le diplôme parent
                $diplome = $diplome->getParent();
            }

            $this->synchroniserApc($diplome, $departement);
        } else {
            //on pioche dans ORéOF
        }

        return true;
    }

    private function synchroniserApc(StructureDiplome $diplome, StructureDepartement $departement): void
    {
        // Récupération des compétences depuis l'API OReOF
        $response = $this->httpClient->request('GET', 'https://orebut.iut.fr/api/specialite/'.$diplome->getSigle().'/competences', [
            'timeout' => 600,
            'headers' => [
                'Accept' => 'application/ld+json',
                ]
        ]);

        $competences = json_decode($response->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erreur lors de la décodage de la réponse JSON: ' . json_last_error_msg());
        }

        $competences = LooseValue::rows(LooseValue::row($competences)["hydra:member"] ?? []);

        // créer le référentiel de compétences
        $refCompetences = new ApcReferentiel();
        $refCompetences->setLibelle($diplome->getLibelle() ?? '');
        $refCompetences->setDepartement($departement);
        $refCompetences->setTypeDiplome($diplome->getTypeDiplome());
        $this->entityManager->persist($refCompetences);

        foreach ($competences as $competence) {
            $comp = new ApcCompetence();
            $comp->setReferentiel($refCompetences);
            $comp->setNomCourt(LooseValue::nullableString($competence['nom_court']));
            $comp->setLibelle(LooseValue::string($competence['libelle']));
            $comp->setCouleur(LooseValue::nullableString($competence['couleur']));
            $tabComposantes = [];
            foreach (LooseValue::rows($competence['apcComposanteEssentielles'] ?? []) as $composanteEssentielle) {
                $tabComposantes[] = [
                    'libelle' => $composanteEssentielle['libelle'],
                    'code' => $composanteEssentielle['code'],
                    'ordre' => $composanteEssentielle['ordre']];
            }
            $comp->setComposantesEssentielles($tabComposantes);
            $tabSitPro = [];
            foreach (LooseValue::rows($competence['apcSituationProfessionnelles'] ?? []) as $situationProfessionnelle) {
                $tabSitPro[] = [
                    'libelle' => $situationProfessionnelle['libelle'],
                ];
            }
            $comp->setSituationsProfessionnelles($tabSitPro);

            foreach (LooseValue::rows($competence['apcNiveaux'] ?? []) as $apcNiveau) {
                $apcN = new ApcNiveau($comp);
                $apcN->setLibelle(LooseValue::string($apcNiveau['libelle']));
                $apcN->setOrdre(LooseValue::int($apcNiveau['ordre']));
                //todo: gestion de l'année associée
                foreach (LooseValue::rows($apcNiveau['apcApprentissageCritiques'] ?? []) as $ac) {
                    $apcAc = new ApcApprentissageCritique($apcN);
                    $apcAc->setLibelle(LooseValue::string($ac['libelle']));
                    $apcAc->setCode(LooseValue::nullableString($ac['code']));
                    $this->entityManager->persist($apcAc);
                }

                $this->entityManager->persist($apcN);
            }
            $this->entityManager->persist($comp);
        }

        $this->entityManager->flush();
    }
}
