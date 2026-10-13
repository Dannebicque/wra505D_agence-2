<?php

declare(strict_types=1);

namespace App\Tests\Service\CourseLog;

use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEvaluation;
use App\Entity\Users\Personnel;
use App\Enum\EtatEvaluationEnum;
use App\Enum\TypeEnseignementEnum;
use App\Enum\TypeEvaluationEnum;
use App\Service\CourseLog\CourseLogAssembler;
use IntranetBundle\Entity\PlanCours\PlanCours;
use IntranetBundle\Entity\PlanCours\PlanCoursRealise;
use IntranetBundle\Entity\PlanCours\PlanCoursSequence;
use IntranetBundle\Enum\PlanCours\PlanCoursEnum;
use PHPUnit\Framework\TestCase;

final class CourseLogAssemblerTest extends TestCase
{
    private CourseLogAssembler $assembler;

    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->assembler = new CourseLogAssembler();
        $this->today = new \DateTimeImmutable('2026-10-13');
    }

    private function teaching(string $code, string $label, TypeEnseignementEnum $type = TypeEnseignementEnum::TYPE_RESSOURCE): ScolEnseignement
    {
        return (new ScolEnseignement())->setCodeEnseignement($code)->setLibelle($label)->setType($type);
    }

    private function evaluation(string $label, ?string $date, EtatEvaluationEnum $state = EtatEvaluationEnum::ETAT_PLANIFIEE): ScolEvaluation
    {
        $evaluation = (new ScolEvaluation())
            ->setLibelle($label)
            ->setCoeff(2.0)
            ->setDate(null === $date ? null : new \DateTime($date))
            ->setEnseignement($this->teaching('R1.11', 'Développement web'))
            ->setEtat($state);
        $evaluation->setType(TypeEvaluationEnum::TYPE_EVALUATION_PROJET);

        return $evaluation;
    }

    private function plan(ScolEnseignement $teaching, PlanCoursEnum $state = PlanCoursEnum::COMPLET): PlanCours
    {
        return (new PlanCours())->setEnseignement($teaching)->setEtatPlanCours($state);
    }

    public function testKeepsEvaluationsDatedTodayOrLater(): void
    {
        $deadlines = $this->assembler->deadlines([
            $this->evaluation('Hier', '2026-10-12'),
            $this->evaluation('Aujourd\'hui', '2026-10-13 08:00'),
            $this->evaluation('Demain', '2026-10-14'),
            $this->evaluation('Sans date', null),
        ], $this->today);

        self::assertSame(['Aujourd\'hui', 'Demain'], array_column($deadlines, 'libelle'));
    }

    public function testLeavesOutCancelledEvaluation(): void
    {
        $deadlines = $this->assembler->deadlines([
            $this->evaluation('Annulée', '2026-10-20', EtatEvaluationEnum::ETAT_ANNULEE),
            $this->evaluation('Maintenue', '2026-10-20'),
        ], $this->today);

        self::assertSame(['Maintenue'], array_column($deadlines, 'libelle'));
    }

    public function testSortsDeadlinesByDate(): void
    {
        $deadlines = $this->assembler->deadlines([
            $this->evaluation('Plus tard', '2026-11-30'),
            $this->evaluation('Bientôt', '2026-10-15'),
        ], $this->today);

        self::assertSame(['2026-10-15', '2026-11-30'], array_column($deadlines, 'date'));
    }

    public function testDescribesDeadlineWithItsSubject(): void
    {
        $deadlines = $this->assembler->deadlines([$this->evaluation('Mini-site', '2026-10-23')], $this->today);

        self::assertSame([[
            'date' => '2026-10-23',
            'libelle' => 'Mini-site',
            'type' => 'Projet',
            'coefficient' => 2.0,
            'code' => 'R1.11',
            'matiere' => 'Développement web',
        ]], $deadlines);
    }

    public function testShowsOnlyCompletePlans(): void
    {
        $subjects = $this->assembler->subjects([
            $this->plan($this->teaching('R1.01', 'Anglais'), PlanCoursEnum::EN_COURS),
            $this->plan($this->teaching('R1.02', 'Culture numérique'), PlanCoursEnum::NONE),
            $this->plan($this->teaching('R1.11', 'Développement web')),
        ]);

        self::assertSame(['R1.11'], array_column($subjects, 'code'));
    }

    public function testSortsSubjectsByCode(): void
    {
        $subjects = $this->assembler->subjects([
            $this->plan($this->teaching('SAE1.01', 'Recommandation', TypeEnseignementEnum::TYPE_SAE)),
            $this->plan($this->teaching('R1.11', 'Développement web')),
        ]);

        self::assertSame(['R1.11', 'SAE1.01'], array_column($subjects, 'code'));
        self::assertSame(['Ressource', 'SAÉ'], array_column($subjects, 'type'));
    }

    public function testExposesPlanContentWithoutStaffFollowUp(): void
    {
        $plan = $this->plan($this->teaching('R1.11', 'Développement web'))
            ->setResponsable((new Personnel())->setPrenom('John')->setNom('DOE'))
            ->setObjectifs('  Écrire une page accessible.  ')
            ->setModalitesEvaluations('Deux TP notés.')
            ->setLivrables('')
            ->setPlanSuiviCommentaire('Réservé à l\'équipe')
            ->setCommentairesStep1('Réservé à l\'équipe')
            ->addPlanCoursSequence((new PlanCoursSequence())->setOrdre(1)->setProgramme('HTML sémantique')->setNbHeures('3 h')->setCommentaire('Interne'))
            ->addPlanCoursRealise((new PlanCoursRealise())->setContenu('Balises de structure')->setNbHeures(1.5)->setCommentaire('Interne'));

        $subjects = $this->assembler->subjects([$plan]);

        self::assertSame([[
            'code' => 'R1.11',
            'libelle' => 'Développement web',
            'type' => 'Ressource',
            'responsable' => 'John DOE',
            'objectifs' => 'Écrire une page accessible.',
            'modalitesEvaluations' => 'Deux TP notés.',
            'livrables' => null,
            'deroule' => null,
            'sequences' => [['ordre' => 1, 'programme' => 'HTML sémantique', 'duree' => '3 h']],
            'seances' => [['contenu' => 'Balises de structure', 'heures' => 1.5]],
        ]], $subjects);
    }
}
