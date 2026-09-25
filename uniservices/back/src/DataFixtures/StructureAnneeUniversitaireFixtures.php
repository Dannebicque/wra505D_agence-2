<?php

namespace App\DataFixtures;

use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Repository\PersonnelRepository;
use App\Repository\Structure\StructurePnRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class StructureAnneeUniversitaireFixtures extends Fixture implements OrderedFixtureInterface
{
    private PersonnelRepository $personnelRepository;
    private StructurePnRepository $pnRepository;

    public function __construct(
        PersonnelRepository          $personnelRepository,
        StructurePnRepository        $pnRepository
    ) {
        $this->personnelRepository = $personnelRepository;
        $this->pnRepository = $pnRepository;
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): int
    {
        return 7;
    }

    /**
     * Libellé de l'année universitaire en cours, décalée de $decalage années.
     *
     * L'année est calculée à partir de la date du jour, avec la même règle que la fausse base
     * Celcat : sans cela, les créneaux de la rentrée tombent dans une année figée.
     */
    public static function libelle(int $decalage = 0): string
    {
        $debut = self::anneeDeDebut() + $decalage;

        return $debut.'/'.($debut + 1);
    }

    /**
     * L'année bascule à la mi-août, comme dans CelcatFausseBaseCommand::lundiDeLaRentree().
     */
    private static function anneeDeDebut(): int
    {
        $aujourdhui = new \DateTimeImmutable('today');

        return (int) $aujourdhui->format('n') >= 8 && (int) $aujourdhui->format('j') >= 15 || (int) $aujourdhui->format('n') > 8
            ? (int) $aujourdhui->format('Y')
            : (int) $aujourdhui->format('Y') - 1;
    }

    public function load(ObjectManager $manager): void
    {
        $personnel = $this->personnelRepository->findOneBy(['username' => 'personnel']);

        $pn1 = $this->pnRepository->findOneBy(['libelle' => 'PN BUT MMI']);
        $pn2 = $this->pnRepository->findOneBy(['libelle' => 'PN BUT MMI DWEB']);

        $anneeUniversitaire1 = new StructureAnneeUniversitaire();
        $anneeUniversitaire1
            ->setLibelle(self::libelle(-1))
            ->setAnnee(self::anneeDeDebut() - 1)
            ->addPn($pn1)
            ->addPn($pn2)
            ->addPersonnel($personnel)
            ->setActif(false)
        ;
        $manager->persist($anneeUniversitaire1);

        $anneeUniversitaire2 = new StructureAnneeUniversitaire();
        $anneeUniversitaire2->setLibelle(self::libelle())
            ->setAnnee(self::anneeDeDebut())
            ->addPn($pn1)
            ->addPn($pn2)
            ->addPersonnel($personnel)
            ->setActif(true)
        ;
        $manager->persist($anneeUniversitaire2);

        $manager->flush();
    }
}
