<?php

namespace IntranetBundle\State\Provider\Previsionnel;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use IntranetBundle\Dto\Previsionnel\PrevisionnelSemestreDto;
use IntranetBundle\Entity\Previsionnel\Previsionnel;

/** @implements ProviderInterface<PrevisionnelSemestreDto> */
class PrevisionnelSemestreTestProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $collectionProvider,
    ) {
    }

    /**
     * Renvoie des tableaux, qu'API Platform sérialise tels quels, et non des ressources.
     *
     * @return array<mixed>|object|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $data = $this->collectionProvider->provide($operation, $uriVariables, $context);

            if (empty($data)) {
                return [];
            }

            $prevSem = [];
            $output = [
                'prevSem' => [],
                'heures' => [],
                'groupes' => [],
            ];
            foreach ($data as $item) {
                if (!$item instanceof \IntranetBundle\Entity\Previsionnel\Previsionnel) {
                    throw new \LogicException('Expected a Previsionnel.');
                }
                if ($item->getPersonnel() !== null && $item->getEnseignement() !== null) {
                    $enseignementId = $item->getEnseignement()->getId();
                    $personnelId = $item->getPersonnel()->getId();
                    if ($enseignementId === null || $personnelId === null) {
                        throw new \LogicException('Entity ID is required');
                    }
                    if (!array_key_exists($enseignementId, $output['heures'])) {
                        $output['heures'][$enseignementId] = [];
                    }
                    if (!array_key_exists($personnelId, $output['heures'][$enseignementId])) {
                        $output['heures'][$enseignementId][$personnelId] = [];
                    }

                    if (!array_key_exists($enseignementId, $output['groupes'])) {
                        $output['groupes'][$enseignementId] = [];
                    }
                    if (!array_key_exists($personnelId, $output['groupes'][$enseignementId])) {
                        $output['groupes'][$enseignementId][$personnelId] = [];
                    }
                    $output['prevSem'][] = $this->formToDto($item);
                    $output['heures'][$enseignementId][$personnelId] = $item->getHeures();
                    $output['groupes'][$enseignementId][$personnelId] = $item->getGroupes();
                }
            }

            return $output;

        } else {
            return [];
        }
    }

    public function formToDto(\IntranetBundle\Entity\Previsionnel\Previsionnel $item): PrevisionnelSemestreDto
    {
        $prevSem = new PrevisionnelSemestreDto();
        $id = $item->getId();
        $enseignement = $item->getEnseignement();
        $personnel = $item->getPersonnel();
        if (null === $id || null === $enseignement || null === $personnel) {
            throw new \LogicException('Previsionnel without id, enseignement or personnel.');
        }
        $enseignementId = $enseignement->getId();
        $codeEnseignement = $enseignement->getCodeEnseignement();
        $personnelId = $personnel->getId();
        if (null === $enseignementId || null === $codeEnseignement || null === $personnelId) {
            throw new \LogicException('Enseignement without id or code, or personnel without id.');
        }
        $prevSem->setId($id);
        $prevSem->setIdEnseignement($enseignementId);
        $prevSem->setCodeEnseignement($codeEnseignement);
        $prevSem->setLibelleEnseignement($enseignement->getDisplay());
        $prevSem->setTypeEnseignement($enseignement->getType());
        $prevSem->setIdPersonnel($personnelId);
        $prevSem->setPersonnels([$personnel]);
        $prevSem->setIntervenant($personnel->getDisplay());
        $prevSem->setHeures(
            [
                'CM' => [
                    'NbHrGrp' => round($item->getGroupes()['CM'] !== 0 ? $item->getHeures()['CM'] / $item->getGroupes()['CM'] : $item->getHeures()['CM'], 1),
                    'NbGrp' => $item->getGroupes()['CM'],
                    'NbSeanceGrp' => round((($item->getGroupes()['CM'] !== 0 ? $item->getHeures()['CM'] / $item->getGroupes()['CM'] : $item->getHeures()['CM']) / Previsionnel::DUREE_SEANCE) * $item->getGroupes()['CM'], 0),
                ],
                'TD' => [
                    'NbHrGrp' => round($item->getGroupes()['TD'] !== 0 ? $item->getHeures()['TD'] / $item->getGroupes()['TD'] : $item->getHeures()['TD'], 1),
                    'NbGrp' => $item->getGroupes()['TD'],
                    'NbSeanceGrp' => round((($item->getGroupes()['TD'] !== 0 ? $item->getHeures()['TD'] / $item->getGroupes()['TD'] : $item->getHeures()['TD']) / Previsionnel::DUREE_SEANCE) * $item->getGroupes()['TD'], 0),
                ],
                'TP' => [
                    'NbHrGrp' => round($item->getGroupes()['TP'] !== 0 ? $item->getHeures()['TP'] / $item->getGroupes()['TP'] : $item->getHeures()['TP'], 1),
                    'NbGrp' => $item->getGroupes()['TP'],
                    'NbSeanceGrp' => round((($item->getGroupes()['TP'] !== 0 ? $item->getHeures()['TP'] / $item->getGroupes()['TP'] : $item->getHeures()['TP']) / Previsionnel::DUREE_SEANCE) * $item->getGroupes()['TP'], 0),
                ],
                'Projet' => [
                    'NbHrGrp' => $item->getHeures()['Projet'],
                    'NbGrp' => $item->getGroupes()['Projet'],
                    'NbSeanceGrp' => ($item->getHeures()['Projet'] / Previsionnel::DUREE_SEANCE),
                ],
            ]
        );
        $prevSem->setGroupes($item->getGroupes());
        return $prevSem;
    }
}
