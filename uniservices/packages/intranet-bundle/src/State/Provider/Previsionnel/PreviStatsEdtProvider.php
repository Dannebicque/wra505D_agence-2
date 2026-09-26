<?php

namespace IntranetBundle\State\Provider\Previsionnel;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use IntranetBundle\Dto\Previsionnel\PreviStatsEdtDto;
use App\Repository\Edt\EdtEventRepository;
use App\Utils\LooseValue;

/** @implements ProviderInterface<object> */
class PreviStatsEdtProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $collectionProvider,
        private ItemProvider $itemProvider,
        private EdtEventRepository $edtEventRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $data = $this->collectionProvider->provide($operation, $uriVariables, $context);

            $dto = new PreviStatsEdtDto();
            if (!(bool) $data) {
                return $dto;
            }

            // Détermination dynamique des types de groupes à partir des lignes de prévisionnel
            $typesSet = [];
            foreach ($data as $previ) {
                if (!$previ instanceof \IntranetBundle\Entity\Previsionnel\Previsionnel) {
                    throw new \LogicException('Expected a Previsionnel.');
                }
                $heures = $previ->getHeures();
                $groupes = $previ->getGroupes();
                $keys = array_unique(array_merge(array_keys($heures), array_keys($groupes)));
                foreach ($keys as $k) {
                    if ($k !== '') {
                        $typesSet[$k] = true;
                    }
                }
            }
            $typesList = array_keys($typesSet);

            // Prévisionnel: heures par enseignement (clé = id) et par type
            $previByEnsType = [];
            // Mapping affichage par id d'enseignement
            $ensDisplayById = [];
            // Prévisionnel: heures par enseignant et par type (utilise la même logique que PrevisionnelPersonnelProvider)
            $previByEnseignantType = [];

            foreach ($data as $previ) {
                if (!$previ instanceof \IntranetBundle\Entity\Previsionnel\Previsionnel) {
                    throw new \LogicException('Expected a Previsionnel.');
                }
                $enseignement = $previ->getEnseignement();
                $ensId = $enseignement?->getId();
                if ($ensId !== null && $ensId !== 0) {
                    $libelleDisplay = ($enseignement->getCodeEnseignement() ?? '').'-'.$enseignement->getLibelle();
                    $ensDisplayById[$ensId] = $libelleDisplay;
                    if (!isset($previByEnsType[$ensId])) {
                        $previByEnsType[$ensId] = [];
                    }
                    $heures = $previ->getHeures();
                    $groupes = $previ->getGroupes();
                    foreach ($typesList as $t) {
                        $h = $heures[$t] ?? 0.0;
                        $g = array_key_exists($t, $groupes) ? $groupes[$t] : 1;
                        if (!isset($previByEnsType[$ensId][$t])) {
                            $previByEnsType[$ensId][$t] = 0.0;
                        }
                        $previByEnsType[$ensId][$t] += $h * $g;
                    }
                }

                $enseignantDisplay = $previ->getPersonnel()?->getDisplay();
                if ($enseignantDisplay !== null && $enseignantDisplay !== '' && $enseignantDisplay !== '0') {
                    if (!isset($previByEnseignantType[$enseignantDisplay])) {
                        $previByEnseignantType[$enseignantDisplay] = [];
                    }
                    $heures = $previ->getHeures();
                    $groupes = $previ->getGroupes();
                    foreach ($typesList as $t) {
                        $h = $heures[$t] ?? 0.0;
                        $g = array_key_exists($t, $groupes) ? $groupes[$t] : 1;
                        if (!isset($previByEnseignantType[$enseignantDisplay][$t])) {
                            $previByEnseignantType[$enseignantDisplay][$t] = 0.0;
                        }
                        $previByEnseignantType[$enseignantDisplay][$t] += $h * $g;
                    }
                }
            }

            // EDT: récupérer les événements correspondants via le repository (filtres: semestre et année universitaire)
            $filters = LooseValue::row($context['filters'] ?? []);
            $semestreId = (bool) ($filters['semestre'] ?? false) ? LooseValue::castInt($filters['semestre']) : null;
            $anneeId = (bool) ($filters['annee'] ?? false) ? LooseValue::castInt($filters['annee']) : null;
            $anneeUniversitaireId = (bool) ($filters['anneeUniversitaire'] ?? false) ? LooseValue::castInt($filters['anneeUniversitaire']) : null;

            if ($semestreId !== null && $semestreId !== 0) {
                $events = $this->edtEventRepository->findForStatsBySemestreAndAnneeUniversitaire($semestreId, $anneeUniversitaireId);
            } elseif ($anneeId !== null && $anneeId !== 0) {
                $events = $this->edtEventRepository->findForStatsByAnneeAndAnneeUniversitaire($anneeId, $anneeUniversitaireId);
            } else {
                // Pas de filtre semestre/année: récupérer tous les événements pour l'année universitaire si fournie
                $events = $this->edtEventRepository->findForStatsByAnneeAndAnneeUniversitaire(null, $anneeUniversitaireId);
            }

            $edtByEnsType = [];
            // EDT: heures par enseignant et par type
            $edtByEnseignantType = [];

            foreach ($events as $ev) {
                $start = $ev->getDebut();
                $end = $ev->getFin();
                if (!$start instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface) {
                    continue;
                }
                $interval = $start->diff($end);
                $duration = $interval->h + ($interval->days * 24) + ($interval->i / 60.0);

                $ens = $ev->getEnseignement();
                $ensId = $ens?->getId();
                $type = (string) $ev->getType();
                if ($type === '') {
                    $type = 'UNKNOWN';
                }
                if ($ensId !== null && $ensId !== 0) {
                    if (!isset($edtByEnsType[$ensId])) {
                        $edtByEnsType[$ensId] = [];
                    }
                    if (!isset($edtByEnsType[$ensId][$type])) {
                        $edtByEnsType[$ensId][$type] = 0.0;
                    }
                    $edtByEnsType[$ensId][$type] += $duration;
                    // Mapping affichage par id si pas déjà connu
                    if (!isset($ensDisplayById[$ensId])) {
                        $code = $ens->getCodeEnseignement() ?? '';
                        $lib = $ens->getLibelle();
                        $ensDisplayById[$ensId] = $code.'-'.$lib;
                    }
                }

                $enseignantDisplay = $ev->getPersonnel()?->getDisplay();
                if ($enseignantDisplay !== null && $enseignantDisplay !== '' && $enseignantDisplay !== '0') {
                    if (!isset($edtByEnseignantType[$enseignantDisplay])) {
                        $edtByEnseignantType[$enseignantDisplay] = [];
                    }
                    if (!isset($edtByEnseignantType[$enseignantDisplay][$type])) {
                        $edtByEnseignantType[$enseignantDisplay][$type] = 0.0;
                    }
                    $edtByEnseignantType[$enseignantDisplay][$type] += $duration;
                }
            }

            // Construire les lignes comparatives par enseignement (clé = id) et par type
            $rows = [];
            $allEnsIds = array_unique(array_merge(array_keys($previByEnsType), array_keys($edtByEnsType)));
            foreach ($allEnsIds as $ensId) {
                $display = $ensDisplayById[$ensId] ?? '';

                // Calculer les totaux par enseignement (somme sur tous les types)
                $totalPrevi = 0.0;
                $totalEdt = 0.0;
                foreach ($typesList as $tt) {
                    $totalPrevi += $previByEnsType[$ensId][$tt] ?? 0.0;
                    $totalEdt += $edtByEnsType[$ensId][$tt] ?? 0.0;
                }

                foreach ($typesList as $t) {
                    $previ = $previByEnsType[$ensId][$t] ?? 0.0;
                    $edt = $edtByEnsType[$ensId][$t] ?? 0.0;
                    if ($previ > 0 || $edt > 0) {
                        // Utiliser la différence des totaux (edt total - prévi total) pour l'enseignement
                        $heures_diff = $totalEdt - $totalPrevi;

                        $rows[] = [
                            'id' => $ensId,
                            'enseignement' => $display,
                            'type' => $t,
                            'heures_previsionnel' => $previ,
                            'heures_edt' => $edt,
                            'heures_diff' => $heures_diff,
                        ];
                    }
                }
            }

            // Construire la comparaison par enseignant et par type
            $allTeachers = array_unique(array_merge(array_keys($previByEnseignantType), array_keys($edtByEnseignantType)));
            // Construire $rowsTeachers en profitant de l'ordre des événements (tri DB par personnel.nom/prenom)
            $rowsTeachers = [];
            $seenTeachers = [];
            // On parcourt d'abord les enseignants rencontrés dans les événements pour préserver l'ordre
            foreach ($events as $ev) {
                $enseignantDisplay = $ev->getPersonnel()?->getDisplay();
                if ($enseignantDisplay === null || $enseignantDisplay === '' || $enseignantDisplay === '0') {
                    continue;
                }
                // éviter doublons
                if (isset($seenTeachers[$enseignantDisplay])) {
                    continue;
                }
                $seenTeachers[$enseignantDisplay] = true;

                // --- CHANGEMENT : calculer les totaux par enseignant ---
                $totalPreviTeacher = 0.0;
                $totalEdtTeacher = 0.0;
                if (isset($previByEnseignantType[$enseignantDisplay])) {
                    foreach ($previByEnseignantType[$enseignantDisplay] as $val) {
                        $totalPreviTeacher += $val;
                    }
                }
                if (isset($edtByEnseignantType[$enseignantDisplay])) {
                    foreach ($edtByEnseignantType[$enseignantDisplay] as $val) {
                        $totalEdtTeacher += $val;
                    }
                }
                $totalDiffTeacher = $totalEdtTeacher - $totalPreviTeacher;
                // --- fin changement ---

                foreach ($typesList as $t) {
                    $previ = $previByEnseignantType[$enseignantDisplay][$t] ?? 0.0;
                    $edt = $edtByEnseignantType[$enseignantDisplay][$t] ?? 0.0;
                    if ($previ > 0 || $edt > 0) {
                        $rowsTeachers[] = [
                            'enseignant' => $enseignantDisplay,
                            'type' => $t,
                            'heures_previsionnel' => $previ,
                            'heures_edt' => $edt,
                            // Utiliser la différence des totaux par enseignant
                            'heures_diff' => $totalDiffTeacher,
                        ];
                    }
                }
            }

            // Puis compléter avec les enseignants provenant du prévisionnel qui n'apparaissent pas dans les événements
            foreach ($allTeachers as $teacher) {
                if (isset($seenTeachers[$teacher])) {
                    continue;
                }

                // --- CHANGEMENT : calculer les totaux par enseignant (pour ceux provenant du prévisionnel) ---
                $totalPreviTeacher = 0.0;
                $totalEdtTeacher = 0.0;
                if (isset($previByEnseignantType[$teacher])) {
                    foreach ($previByEnseignantType[$teacher] as $val) {
                        $totalPreviTeacher += $val;
                    }
                }
                if (isset($edtByEnseignantType[$teacher])) {
                    foreach ($edtByEnseignantType[$teacher] as $val) {
                        $totalEdtTeacher += $val;
                    }
                }
                $totalDiffTeacher = $totalEdtTeacher - $totalPreviTeacher;
                // --- fin changement ---

                foreach ($typesList as $t) {
                    $previ = $previByEnseignantType[$teacher][$t] ?? 0.0;
                    $edt = $edtByEnseignantType[$teacher][$t] ?? 0.0;
                    if ($previ > 0 || $edt > 0) {
                        $rowsTeachers[] = [
                            'enseignant' => $teacher,
                            'type' => $t,
                            'heures_previsionnel' => $previ,
                            'heures_edt' => $edt,
                            // Utiliser la différence des totaux par enseignant
                            'heures_diff' => $totalDiffTeacher,
                        ];
                    }
                }
            }

            // Calculer le taux global de réalisation (heures EDT réalisées / heures prévues) sur l'ensemble de la sélection
            $total_previ = 0.0;
            $total_edt = 0.0;

            // Somme des heures prévues (toutes matières, tous types)
            foreach ($previByEnsType as $ensIdTmp => $types) {
                foreach ($types as $t => $val) {
                    $total_previ += $val;
                }
            }

            // Somme des heures réalisées (EDT) (toutes matières, tous types)
            foreach ($edtByEnsType as $ensIdTmp => $types) {
                foreach ($types as $t => $val) {
                    $total_edt += $val;
                }
            }

            if ($total_previ > 0.0) {
                $taux_realisation = ($total_edt / $total_previ) * 100.0;
            } else {
                // S'il n'y a pas de prévisionnel: 100% si on a des heures réalisées, sinon 0%
                $taux_realisation = ($total_edt > 0.0) ? 100.0 : 0.0;
            }

            $dto->setStatPreviEdtEnseignement($rows);
            $dto->setStatPreviEdtEnseignant($rowsTeachers);
            $dto->setTypesGroupes($typesList);
            $dto->setTauxRealisation((int) round($taux_realisation));
            return $dto;
        }

        // Cas item: on effectue un simple pass-through également
        return $this->itemProvider->provide($operation, $uriVariables, $context);
    }
}
