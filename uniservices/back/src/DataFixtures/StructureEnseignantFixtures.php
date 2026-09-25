<?php

namespace App\DataFixtures;

use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Users\Personnel;
use App\Enum\StatutEnum;
use App\Repository\Structure\StructureDepartementRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Enseignants du département MMI qui interviennent dans l'emploi du temps.
 *
 * La fausse base Celcat reprend cette liste : la synchronisation rattache chaque créneau à son
 * intervenant par le numéro Harpege, et la recherche le trouve dans l'annuaire du département.
 */
class StructureEnseignantFixtures extends Fixture implements OrderedFixtureInterface
{
    /**
     * Numéro Harpege, c'est-à-dire le code de l'intervenant dans Celcat, vers nom et prénom.
     *
     * @var array<int, array{nom: string, prenom: string}>
     */
    public const ENSEIGNANTS = [
        10001 => ['nom' => 'Martin', 'prenom' => 'Claire'],
        10002 => ['nom' => 'Lefèvre', 'prenom' => 'Hélène'],
        10003 => ['nom' => 'Garnier', 'prenom' => 'Thomas'],
        10004 => ['nom' => 'Roussel', 'prenom' => 'Inès'],
        10005 => ['nom' => 'Chevalier', 'prenom' => 'Marc'],
    ];

    public function __construct(
        private readonly StructureDepartementRepository $departementRepository,
    ) {
    }

    /**
     * Après les départements, créés à l'ordre 2.
     */
    public function getOrder(): int
    {
        return 3;
    }

    public function load(ObjectManager $manager): void
    {
        $mmi = $this->departementRepository->findOneBy(['libelle' => 'MMI']);
        $slugger = new AsciiSlugger();

        foreach (self::ENSEIGNANTS as $numeroHarpege => ['nom' => $nom, 'prenom' => $prenom]) {
            $identifiant = $slugger->slug($prenom.' '.$nom, '.')->lower()->toString();

            $enseignant = (new Personnel())
                ->setUsername($identifiant)
                ->setMailUniv($identifiant.'@univ-reims.fr')
                ->setStatut(StatutEnum::MCF)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setNumeroHarpege($numeroHarpege)
                ->setPhotoName('noimage.png');
            $manager->persist($enseignant);

            $manager->persist((new StructureDepartementPersonnel())
                ->setPersonnel($enseignant)
                ->setDepartement($mmi)
                ->setPackages(['intranet'])
                ->setPermissions(['ROLE_TEACHER'])
                ->setDefaut(true));
        }

        $manager->flush();
    }
}
