<?php

namespace App\DataFixtures;

use App\Entity\Edt\EdtEvent;
use App\Entity\Etudiant\EtudiantNote;
use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Users\Etudiant;
use App\Enum\EtatEvaluationEnum;
use App\Enum\TypeEvaluationEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use IntranetBundle\Entity\Etudiant\EtudiantAbsenceJustificatif;
use IntranetBundle\Enum\EtatJustificatifEnum;

/**
 * Évaluations, notes et absences de l'étudiant de test, pour la page Scolarité.
 *
 * Chaque cas que la page doit distinguer est présent : note publiée, zéro réel, absence
 * injustifiée, absence justifiée, note saisie mais pas encore publiée, évaluation à venir, et
 * des absences justifiées ou non. Les dates suivent l'année universitaire en cours.
 */
class ScolariteNotesFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int
    {
        return 12;
    }

    public function load(ObjectManager $manager): void
    {
        $etudiant = $manager->getRepository(Etudiant::class)->findOneBy(['username' => 'etudiant']);
        $annee = $manager->getRepository(StructureAnneeUniversitaire::class)
            ->findOneBy(['libelle' => StructureAnneeUniversitaireFixtures::libelle()]);
        $scolarite = $manager->getRepository(EtudiantScolarite::class)->findOneBy(['etudiant' => $etudiant, 'actif' => true]);
        $scolariteSemestre = $scolarite?->getScolariteSemestre()->first();
        if (!$annee || !$scolarite || !$scolariteSemestre) {
            throw new \RuntimeException('Scolarité active de l\'étudiant de test introuvable.');
        }

        $rentree = new \DateTimeImmutable(substr((string) $annee->getLibelle(), 0, 4).'-09-01');
        $enseignements = $manager->getRepository(ScolEnseignement::class)->findAll();
        $parCode = [];
        foreach ($enseignements as $enseignement) {
            $parCode[$enseignement->getCodeEnseignement()] = $enseignement;
        }

        // [matière, libellé, coefficient, type, jours après la rentrée, état, note, statut de présence]
        $evaluations = [
            ['R1.01', 'Compréhension orale', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_EXAM, 10, EtatEvaluationEnum::ETAT_PUBLIEE, 14.5, EtudiantNote::STATUT_PRESENT],
            ['R1.01', 'Expression écrite', 2.0, TypeEvaluationEnum::TYPE_EVALUATION_EXAM, 17, EtatEvaluationEnum::ETAT_PUBLIEE, 0.0, EtudiantNote::STATUT_PRESENT],
            ['R1.11', 'TP intégration', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_TP, 12, EtatEvaluationEnum::ETAT_PUBLIEE, 16.0, EtudiantNote::STATUT_PRESENT],
            ['R1.11', 'TP formulaires', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_TP, 19, EtatEvaluationEnum::ETAT_PUBLIEE, null, EtudiantNote::STATUT_ABSENT_INJUSTIFIE],
            ['R1.06', 'Affiche', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_PROJET, 15, EtatEvaluationEnum::ETAT_PUBLIEE, null, EtudiantNote::STATUT_ABSENT_JUSTIFIE],
            ['R1.06', 'Charte graphique', 2.0, TypeEvaluationEnum::TYPE_EVALUATION_PROJET, 20, EtatEvaluationEnum::ETAT_PUBLIEE, 12.0, EtudiantNote::STATUT_PRESENT],
            ['SAE1.01', 'Soutenance', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_PROJET, 21, EtatEvaluationEnum::ETAT_COMPLETEE, 17.0, EtudiantNote::STATUT_PRESENT],
            ['R1.02', 'Examen', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_EXAM, 60, EtatEvaluationEnum::ETAT_PLANIFIEE, null, null],
        ];

        foreach ($evaluations as [$code, $libelle, $coefficient, $type, $jours, $etat, $valeur, $statut]) {
            $evaluation = new ScolEvaluation();
            $evaluation->setLibelle($libelle)
                ->setCoeff($coefficient)
                ->setDate(\DateTime::createFromImmutable($rentree->modify("+$jours days")))
                ->setVisible(true)
                ->setEnseignement($parCode[$code])
                ->setSemestre($scolariteSemestre->getSemestre())
                ->setAnneeUniversitaire($annee)
                ->setEtat($etat);
            $evaluation->setType($type);
            $manager->persist($evaluation);

            if (null === $statut) {
                continue;
            }
            $note = new EtudiantNote();
            $note->setEvaluation($evaluation)
                ->setScolarite($scolarite)
                ->setScolariteSemestre($scolariteSemestre)
                ->setPresenceStatut($statut);
            if (null !== $valeur) {
                $note->setNote($valeur);
            }
            $manager->persist($note);
        }

        // [matière, jours après la rentrée, heure de début, justificatif]
        $absences = [
            ['R1.03', 3, '10:15', EtatJustificatifEnum::VALIDE],
            ['R1.10', 9, '13:30', null],
            ['R1.02', 16, '08:00', EtatJustificatifEnum::EN_ATTENTE],
        ];
        foreach ($absences as [$code, $jours, $heure, $etatJustificatif]) {
            $debut = $rentree->modify("+$jours days $heure");
            $event = new EdtEvent();
            $event->setDate(\DateTime::createFromImmutable($debut->setTime(0, 0)))
                ->setDebut(\DateTime::createFromImmutable($debut))
                ->setFin(\DateTime::createFromImmutable($debut->modify('+2 hours')))
                ->setSalle('B204')
                ->setEnseignement($parCode[$code])
                ->setCodeModule($code)
                ->setLibModule($parCode[$code]->getLibelle())
                ->setAnneeUniversitaire($annee)
                ->setEvaluation(false)
                ->setAPlacer(false);
            $manager->persist($event);

            $absence = new EtudiantAbsence();
            $absence->setEvent($event)->setScolariteSemestre($scolariteSemestre);
            if (null !== $etatJustificatif) {
                $justificatif = new EtudiantAbsenceJustificatif();
                $justificatif->setDebut(\DateTime::createFromImmutable($debut->setTime(0, 0)));
                $justificatif->setFin(\DateTime::createFromImmutable($debut->setTime(23, 59)));
                $justificatif->setScolariteSemestre($scolariteSemestre)
                    ->setEtat($etatJustificatif)
                    ->setMotif('Rendez-vous médical');
                $manager->persist($justificatif);
                $absence->setAbsenceJustificatif($justificatif);
            }
            $manager->persist($absence);
        }

        $manager->flush();
    }
}
