<?php

namespace App\DataFixtures;

use App\Entity\DepartementActualite;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Scolarite\ScolEnseignementUe;
use App\Entity\Structure\StructureUe;
use App\Enum\TypeEnseignementEnum;
use App\Enum\TypePublicEnum;
use App\Repository\Structure\StructureDepartementRepository;
use App\Repository\Structure\StructureSemestreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Matières du S1 et actualités du département, pour la recherche et l'emploi du temps.
 *
 * Les codes sont ceux de la fausse base Celcat (CelcatFausseBaseCommand) : la synchronisation
 * rattache ainsi ses créneaux à une matière.
 */
class StructureEnseignementFixtures extends Fixture implements OrderedFixtureInterface
{
    private const MATIERES_S1 = [
        'R1.01' => 'Anglais',
        'R1.02' => 'Culture numérique',
        'R1.03' => 'Stratégies de communication',
        'R1.05' => 'Écriture multimédia et narration',
        'R1.06' => 'Production graphique',
        'R1.10' => 'Intégration',
        'R1.11' => 'Développement web',
        'SAE1.01' => 'Recommandation de communication numérique',
    ];

    /** Coefficient de chaque matière dans l'UE : la SAÉ pèse plus que les ressources. */
    private const COEFFICIENTS = ['SAE1.01' => 3.0, 'R1.11' => 2.0, 'R1.06' => 2.0];

    public function __construct(
        private readonly StructureSemestreRepository $semestreRepository,
        private readonly StructureDepartementRepository $departementRepository,
    ) {
    }

    public function getOrder(): int
    {
        return 11;
    }

    public function load(ObjectManager $manager): void
    {
        $s1 = $this->semestreRepository->findOneBy(['libelle' => 'S1']);

        $ue = new StructureUe();
        $ue->setLibelle('UE 1.1 Comprendre')
            ->setNumero(1)
            ->setCodeElement('MMI1UE11')
            ->setSemestre($s1);
        $manager->persist($ue);

        foreach (self::MATIERES_S1 as $code => $libelle) {
            $enseignement = new ScolEnseignement();
            $enseignement->setLibelle($libelle)
                ->setCodeEnseignement($code)
                ->setType(str_starts_with($code, 'SAE') ? TypeEnseignementEnum::TYPE_SAE : TypeEnseignementEnum::TYPE_RESSOURCE)
                ->setHeures([
                    'CM' => ['PN' => 0, 'IUT' => 0],
                    'TD' => ['PN' => 0, 'IUT' => 0],
                    'TP' => ['PN' => 0, 'IUT' => 0],
                ]);
            $manager->persist($enseignement);

            $lien = new ScolEnseignementUe($enseignement, $ue);
            $lien->setCoefficient(self::COEFFICIENTS[$code] ?? 1.0);
            $ue->addEnseignementUe($lien);
            $manager->persist($lien);
        }

        $departement = $this->departementRepository->findOneBy(['libelle' => 'MMI']);
        $actualites = [
            ['Réunion de rentrée des MMI 1', 'Présentation de l\'année, de l\'équipe et des emplois du temps.', TypePublicEnum::TYPE_PUBLIC_ETUDIANT],
            ['Conseil de département', 'Ordre du jour : bilan de la rentrée et projets tutorés.', TypePublicEnum::TYPE_PUBLIC_PERSONNEL],
        ];
        foreach ($actualites as [$libelle, $description, $public]) {
            $actualite = new DepartementActualite();
            $actualite->setLibelle($libelle)
                ->setDescription($description)
                ->setDepartement($departement)
                ->setPublic([$public])
                ->setDateDebut(new \DateTime('today'))
                ->setDateFin(new \DateTime('+1 month'));
            $manager->persist($actualite);
        }

        $manager->flush();
    }
}
