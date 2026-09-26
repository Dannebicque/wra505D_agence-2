<?php

namespace App\DataFixtures;

use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Repository\EtudiantRepository;
use App\Repository\Structure\StructureAnneeUniversitaireRepository;
use App\Repository\Structure\StructureDepartementRepository;
use App\Repository\Structure\StructureGroupeRepository;
use App\Repository\Structure\StructureSemestreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class StructureScolariteFixtures extends Fixture implements OrderedFixtureInterface
{
    private StructureSemestreRepository $semestreRepository;
    private EtudiantRepository $etudiantRepository;

    private StructureAnneeUniversitaireRepository $anneeUniversitaireRepository;

    public function __construct(
        StructureSemestreRepository           $semestreRepository,
        EtudiantRepository                    $etudiantRepository,
        StructureAnneeUniversitaireRepository $anneeUniversitaireRepository,
        private readonly StructureGroupeRepository $groupeRepository,
        private readonly StructureDepartementRepository $departementRepository,
    ) {
        $this->semestreRepository = $semestreRepository;
        $this->etudiantRepository = $etudiantRepository;
        $this->anneeUniversitaireRepository = $anneeUniversitaireRepository;
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): int
    {
        // Après StructureGroupeFixtures, qui crée les groupes de S1.
        return 10;
    }

    public function load(ObjectManager $manager): void
    {
        $semestre1 = $this->semestreRepository->findOneBy(['libelle' => 'S1']);

        $etu1 = $this->etudiantRepository->findOneBy(['username' => 'etudiant']);

        $anneeUniversitaire1 = $this->anneeUniversitaireRepository->findOneBy(['libelle' => StructureAnneeUniversitaireFixtures::libelle(-1)]);
        $anneeUniversitaire2 = $this->anneeUniversitaireRepository->findOneBy(['libelle' => StructureAnneeUniversitaireFixtures::libelle()]);

        if (null === $semestre1 || null === $etu1 || null === $anneeUniversitaire1 || null === $anneeUniversitaire2) {
            throw new \LogicException('Les données nécessaires aux scolarités sont introuvables.');
        }

        $scolarite1 = new EtudiantScolarite();
        $scolarite1->setEtudiant($etu1)
            ->setOrdre(1)
            ->setNbAbsences(0)
            ->setPublic(true)
            ->setAnneeUniversitaire($anneeUniversitaire1)
            ->setUuid(Uuid::v4())
        ;

        $scolariteSemestre1 = new EtudiantScolariteSemestre();
        $scolariteSemestre1->setSemestre($semestre1)
            ->setScolarite($scolarite1);

        $scolarite1->addScolariteSemestre($scolariteSemestre1);
        $manager->persist($scolarite1);
        $manager->persist($scolariteSemestre1);

        // Scolarité en cours, sur l'année et les groupes de l'emploi du temps simulé par Celcat.
        $scolarite2 = new EtudiantScolarite();
        $scolarite2->setEtudiant($etu1)
            ->setOrdre(2)
            ->setNbAbsences(0)
            ->setPublic(true)
            ->setAnneeUniversitaire($anneeUniversitaire2)
            ->setDepartement($this->departementRepository->findOneBy(['libelle' => 'MMI']))
            ->setUuid(Uuid::v4());
        $scolarite2->setActif(true);

        $scolariteSemestre2 = new EtudiantScolariteSemestre();
        $scolariteSemestre2->setSemestre($semestre1)
            ->setScolarite($scolarite2);

        foreach (['MMICM', 'MMITDAB', 'MMITPA'] as $codeGroupe) {
            $groupe = $this->groupeRepository->findOneBy(['codeApogee' => $codeGroupe]);
            if (null === $groupe) {
                throw new \LogicException('Un groupe nécessaire à la scolarité est introuvable.');
            }
            $scolariteSemestre2->addGroupe($groupe);
            $etu1->addGroupe($groupe);
        }

        $scolarite2->addScolariteSemestre($scolariteSemestre2);
        $manager->persist($scolarite2);
        $manager->persist($scolariteSemestre2);

        $manager->flush();
    }
}
