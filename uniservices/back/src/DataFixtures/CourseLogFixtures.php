<?php

namespace App\DataFixtures;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\EtatEvaluationEnum;
use App\Enum\TypeEvaluationEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use IntranetBundle\Entity\PlanCours\PlanCours;
use IntranetBundle\Entity\PlanCours\PlanCoursRealise;
use IntranetBundle\Entity\PlanCours\PlanCoursSequence;
use IntranetBundle\Enum\PlanCours\PlanCoursEnum;

/**
 * Cahier de texte de l'étudiant de test : des évaluations à venir, une annulée, un plan de cours
 * complet et un brouillon.
 *
 * Les dates partent du jour du chargement, pas de la rentrée : une échéance doit rester à venir
 * quelle que soit la date à laquelle les fixtures sont chargées.
 */
class CourseLogFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int
    {
        return 14;
    }

    public function load(ObjectManager $manager): void
    {
        $etudiant = $manager->getRepository(Etudiant::class)->findOneBy(['username' => 'etudiant']);
        $annee = $manager->getRepository(StructureAnneeUniversitaire::class)
            ->findOneBy(['libelle' => StructureAnneeUniversitaireFixtures::libelle()]);
        $scolarite = $manager->getRepository(EtudiantScolarite::class)->findOneBy(['etudiant' => $etudiant, 'actif' => true]);
        $scolariteSemestre = $scolarite?->getScolariteSemestre()->first();
        if (!$annee instanceof StructureAnneeUniversitaire || !(bool) $scolariteSemestre) {
            throw new \RuntimeException('Scolarité active de l\'étudiant de test introuvable.');
        }

        $parCode = [];
        foreach ($manager->getRepository(ScolEnseignement::class)->findAll() as $enseignement) {
            $parCode[$enseignement->getCodeEnseignement()] = $enseignement;
        }

        // [matière, libellé, coefficient, type, jours à partir d'aujourd'hui, état]
        $evaluations = [
            ['SAE1.01', 'Rendu du dossier', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_PROJET, 3, EtatEvaluationEnum::ETAT_PLANIFIEE],
            ['R1.01', 'Oral reporté', 1.0, TypeEvaluationEnum::TYPE_EVALUATION_EXAM, 5, EtatEvaluationEnum::ETAT_ANNULEE],
            ['R1.11', 'Mini-site personnel', 2.0, TypeEvaluationEnum::TYPE_EVALUATION_PROJET, 10, EtatEvaluationEnum::ETAT_PLANIFIEE],
        ];
        $aujourdHui = new \DateTimeImmutable('today');
        foreach ($evaluations as [$code, $libelle, $coefficient, $type, $jours, $etat]) {
            $evaluation = new ScolEvaluation();
            $evaluation->setLibelle($libelle)
                ->setCoeff($coefficient)
                ->setDate(\DateTime::createFromImmutable($aujourdHui->modify("+$jours days")))
                ->setVisible(true)
                ->setEnseignement($parCode[$code])
                ->setSemestre($scolariteSemestre->getSemestre())
                ->setAnneeUniversitaire($annee)
                ->setEtat($etat);
            $evaluation->setType($type);
            $manager->persist($evaluation);
        }

        $complet = new PlanCours();
        $complet->setEnseignement($parCode['R1.11'])
            ->setAnneeUniversitaire($annee)
            ->setResponsable($manager->getRepository(Personnel::class)->findOneBy(['username' => 'personnel']))
            ->setEtatPlanCours(PlanCoursEnum::COMPLET)
            ->setObjectifs('Écrire une page web sémantique, accessible et qui s\'adapte à l\'écran.')
            ->setModalitesEvaluations("Deux TP notés en séance.\nUn mini-site personnel à rendre en fin de module.")
            ->setLivrables('Mini-site personnel de trois pages, déposé sur le dépôt Git de la promotion.')
            ->addPlanCoursSequence((new PlanCoursSequence())->setOrdre(1)->setProgramme('HTML sémantique et structure d\'une page')->setNbHeures('3 h'))
            ->addPlanCoursSequence((new PlanCoursSequence())->setOrdre(2)->setProgramme('Mise en page avec Flexbox et Grid')->setNbHeures('4 h 30'))
            ->addPlanCoursSequence((new PlanCoursSequence())->setOrdre(3)->setProgramme('Formulaires et accessibilité')->setNbHeures('3 h'))
            ->addPlanCoursRealise((new PlanCoursRealise())->setContenu('Balises de structure, titres et listes')->setNbHeures(1.5))
            ->addPlanCoursRealise((new PlanCoursRealise())->setContenu('TP : intégrer une maquette en Flexbox')->setNbHeures(3.0));
        $manager->persist($complet);

        $brouillon = new PlanCours();
        $brouillon->setEnseignement($parCode['R1.01'])
            ->setAnneeUniversitaire($annee)
            ->setEtatPlanCours(PlanCoursEnum::EN_COURS)
            ->setObjectifs('Plan en cours de rédaction.');
        $manager->persist($brouillon);

        $manager->flush();
    }
}
