<?php

namespace App\Service\Scolarite;

use App\Entity\Etudiant\EtudiantNote;
use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureUe;
use App\Entity\Users\Etudiant;
use App\Enum\EtatEvaluationEnum;
use Doctrine\ORM\EntityManagerInterface;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Enum\EtatJustificatifEnum;

/**
 * Relevé de l'année en cours d'un étudiant : UE, matières, évaluations, notes publiées, moyennes
 * provisoires et absences, semestre par semestre.
 *
 * Une note n'est montrée que si son évaluation est publiée : c'est le seul mécanisme de
 * publication que le code utilise, le drapeau EtudiantNote::$publiee n'étant jamais renseigné.
 */
final class ReleveEtudiant
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CalculMoyennes $calculMoyennes,
    ) {
    }

    /**
     * @return array{anneeUniversitaire: ?string, semestres: list<array<string, mixed>>}
     */
    public function pour(Etudiant $etudiant): array
    {
        $scolarite = $this->entityManager->getRepository(EtudiantScolarite::class)
            ->findOneBy(['etudiant' => $etudiant, 'actif' => true]);
        if (null === $scolarite) {
            return ['anneeUniversitaire' => null, 'semestres' => []];
        }

        $semestres = [];
        foreach ($scolarite->getScolariteSemestre() as $scolariteSemestre) {
            if (null !== $scolariteSemestre->getSemestre()) {
                $semestres[] = $this->semestre($scolarite, $scolariteSemestre);
            }
        }

        return [
            'anneeUniversitaire' => $scolarite->getAnneeUniversitaire()?->getLibelle(),
            'semestres' => $semestres,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function semestre(EtudiantScolarite $scolarite, EtudiantScolariteSemestre $scolariteSemestre): array
    {
        $semestre = $scolariteSemestre->getSemestre();
        $ues = $this->entityManager->getRepository(StructureUe::class)->findBy(['semestre' => $semestre], ['numero' => 'ASC']);

        $idsEnseignements = [];
        foreach ($ues as $ue) {
            foreach ($ue->getEnseignementUes() as $enseignementUe) {
                if (null !== $enseignementUe->getEnseignement()?->getId()) {
                    $idsEnseignements[] = $enseignementUe->getEnseignement()->getId();
                }
            }
        }

        $evaluations = $this->evaluationsParEnseignement($idsEnseignements, $scolarite);
        $notes = $this->notesParEvaluation($scolariteSemestre);

        $moyennesEnseignement = [];
        $lignesUe = [];
        foreach ($ues as $ue) {
            $enseignements = [];
            foreach ($ue->getEnseignementUes() as $enseignementUe) {
                $enseignement = $enseignementUe->getEnseignement();
                if (null === $enseignement || null === $enseignement->getId()) {
                    continue;
                }
                $id = $enseignement->getId();
                $lignesEvaluations = [];
                $notesRetenues = [];
                foreach ($evaluations[$id] ?? [] as $evaluation) {
                    $note = $notes[$evaluation->getId()] ?? null;
                    $ligne = $this->evaluation($evaluation, $note);
                    if ('publiee' === $ligne['etat'] && null !== $note) {
                        $notesRetenues[] = [
                            'note' => $this->valeur($note),
                            'coefficient' => $evaluation->getCoeff(),
                            'statut' => $note->getPresenceStatut(),
                        ];
                    }
                    $lignesEvaluations[] = $ligne;
                }
                $moyennesEnseignement[$id] ??= $this->calculMoyennes->moyenneEnseignement($notesRetenues);

                $enseignements[] = [
                    'code' => $enseignement->getCodeEnseignement(),
                    'libelle' => $enseignement->getLibelle(),
                    'type' => $enseignement->getType()->getLibelle(),
                    'coefficient' => $enseignementUe->getCoefficient(),
                    'moyenne' => $moyennesEnseignement[$id],
                    'evaluations' => $lignesEvaluations,
                ];
            }

            $lignesUe[] = [
                'numero' => $ue->getNumero(),
                'libelle' => $ue->getLibelle(),
                'moyenne' => $this->calculMoyennes->moyenneUe(array_map(
                    fn (array $ligne) => ['moyenne' => $ligne['moyenne'], 'coefficient' => $ligne['coefficient']],
                    $enseignements,
                )),
                'enseignements' => $enseignements,
            ];
        }

        return [
            'libelle' => $semestre?->getLibelle(),
            'ues' => $lignesUe,
            'absences' => $this->absences($scolariteSemestre),
        ];
    }

    /**
     * @param list<int> $idsEnseignements
     *
     * @return array<int, list<ScolEvaluation>>
     */
    private function evaluationsParEnseignement(array $idsEnseignements, EtudiantScolarite $scolarite): array
    {
        if ([] === $idsEnseignements) {
            return [];
        }

        $evaluations = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ScolEvaluation::class, 'e')
            ->where('e.enseignement IN (:enseignements)')
            ->andWhere('e.anneeUniversitaire = :annee')
            ->andWhere('e.etat <> :annulee')
            ->setParameter('enseignements', array_unique($idsEnseignements))
            ->setParameter('annee', $scolarite->getAnneeUniversitaire())
            ->setParameter('annulee', EtatEvaluationEnum::ETAT_ANNULEE)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();

        $parEnseignement = [];
        foreach ($evaluations as $evaluation) {
            $parEnseignement[$evaluation->getEnseignement()?->getId()][] = $evaluation;
        }

        return $parEnseignement;
    }

    /**
     * @return array<int, EtudiantNote>
     */
    private function notesParEvaluation(EtudiantScolariteSemestre $scolariteSemestre): array
    {
        $notes = [];
        foreach ($this->entityManager->getRepository(EtudiantNote::class)->findBy(['scolariteSemestre' => $scolariteSemestre]) as $note) {
            if (null !== $note->getEvaluation()?->getId()) {
                $notes[$note->getEvaluation()->getId()] = $note;
            }
        }

        return $notes;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluation(ScolEvaluation $evaluation, ?EtudiantNote $note): array
    {
        $publiee = EtatEvaluationEnum::ETAT_PUBLIEE === $evaluation->getEtat();
        $aVenir = null !== $evaluation->getDate() && $evaluation->getDate() > new \DateTimeImmutable('today');

        return [
            'libelle' => $evaluation->getLibelle(),
            'date' => $evaluation->getDate()?->format('Y-m-d'),
            'coefficient' => $evaluation->getCoeff(),
            'type' => $evaluation->getType()?->value,
            'etat' => $publiee ? 'publiee' : ($aVenir ? 'a_venir' : 'en_attente'),
            'note' => $publiee && null !== $note ? $this->valeur($note) : null,
            'statut' => $publiee ? ($note?->getPresenceStatut() ?? EtudiantNote::STATUT_PRESENT) : null,
        ];
    }

    /**
     * Valeur d'une note présente et saisie. EtudiantNote::getNote() renvoie -0,01 pour une absence
     * justifiée ou une dispense, et 0 pour une absence injustifiée : c'est le statut qui le dit ici.
     */
    private function valeur(EtudiantNote $note): ?float
    {
        if (EtudiantNote::STATUT_PRESENT !== $note->getPresenceStatut()) {
            return null;
        }
        $valeur = $note->getNote();

        return null !== $valeur && $valeur >= 0 ? $valeur : null;
    }

    /**
     * @return array{total: int, justifiees: int, injustifiees: int, enAttente: int, liste: list<array<string, ?string>>}
     */
    private function absences(EtudiantScolariteSemestre $scolariteSemestre): array
    {
        $absences = $this->entityManager->createQueryBuilder()
            ->select('a', 'ev', 'j')
            ->from(EtudiantAbsence::class, 'a')
            ->join('a.event', 'ev')
            ->leftJoin('a.absenceJustificatif', 'j')
            ->where('a.scolariteSemestre = :scolariteSemestre')
            ->setParameter('scolariteSemestre', $scolariteSemestre)
            ->orderBy('ev.debut', 'DESC')
            ->getQuery()
            ->getResult();

        $compteurs = ['validee' => 0, 'aucune' => 0, 'refusee' => 0, 'en_attente' => 0];
        $liste = [];
        foreach ($absences as $absence) {
            $justification = match ($absence->getAbsenceJustificatif()?->getEtat()) {
                EtatJustificatifEnum::VALIDE => 'validee',
                EtatJustificatifEnum::REFUSE => 'refusee',
                EtatJustificatifEnum::EN_ATTENTE => 'en_attente',
                default => 'aucune',
            };
            ++$compteurs[$justification];
            $event = $absence->getEvent();
            $liste[] = [
                'debut' => $event?->getDebut()?->format(\DateTimeInterface::ATOM),
                'fin' => $event?->getFin()?->format(\DateTimeInterface::ATOM),
                'matiere' => trim(($event?->getCodeModule() ?? '').' '.($event?->getLibModule() ?? '')) ?: null,
                'justification' => $justification,
            ];
        }

        return [
            'total' => count($liste),
            'justifiees' => $compteurs['validee'],
            'injustifiees' => $compteurs['aucune'] + $compteurs['refusee'],
            'enAttente' => $compteurs['en_attente'],
            'liste' => $liste,
        ];
    }
}
