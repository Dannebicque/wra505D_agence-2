<?php

namespace App\DataFixtures;

use App\Entity\Scolarite\ScolBac;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Enum\StatutEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StructureUserFixtures extends Fixture implements OrderedFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $encoder,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): int
    {
        return 1;
    }

    public function load(ObjectManager $manager): void
    {
        // ----------- PERSONNEL
        $personnel = new Personnel();
        $password = $this->encoder->hashPassword($personnel, 'test');
        $personnel->setUsername('personnel')
            ->setMailUniv('personnel.user@univ-reims.fr')
            ->setPassword($password)
            ->setStatut(StatutEnum::MCF)
            ->setPrenom('John')
            ->setNom('DOE')
            ->setPhotoName('noimage.png');
        $manager->persist($personnel);

        // ----------- SUPERADMIN
        $superadmin = new Personnel();
        $password = $this->encoder->hashPassword($superadmin, 'test');
        $superadmin->setUsername('superadmin')
            ->setMailUniv('superadmin@univ-reims.fr')
            ->setPassword($password)
            ->setStatut(StatutEnum::MCF)
            ->setPrenom('Super')
            ->setNom('ADMIN')
            ->setPhotoName('noimage.png')
        ;
        $manager->persist($superadmin);

        // ----------- ETUDIANT
        $bac = new ScolBac();
        $bac->setLibelle('Bac général')
            ->setLibelleLong('Baccalauréat général, spécialités NSI et mathématiques')
            ->setTypeBac('G');
        $manager->persist($bac);

        // Entrée en première année l'an dernier, bac en poche la même année : les âges et les
        // années suivent l'année universitaire en cours, comme les scolarités.
        $entree = (int) substr(StructureAnneeUniversitaireFixtures::libelle(-1), 0, 4);

        $etudiant = new Etudiant();
        $password = $this->encoder->hashPassword($etudiant, 'test');
        $etudiant->setUsername('etudiant')
            ->setMailUniv('etudiant.user@etudiant.univ-reims.fr')
            ->setPassword($password)
            ->setRoles(['ROLE_ETUDIANT'])
            ->setPrenom('Jane')
            ->setNom('Doe')
            ->setBoursier(false)
            ->setApplications(['UniTranet'])
            ->setPhotoName('noimage.png')
            ->setDateNaissance(new \DateTimeImmutable(($entree - 18).'-03-21'))
            ->setLieuNaissance('Reims')
            ->setBac($bac)
            ->setAnneeBac($entree)
            ->setPromotion($entree)
            ->setNumEtudiant('22501437')
            ->setNumIne('071234567HK')
            ->setSiteUniv('https://mmi25b12.mmi-troyes.fr')
            ->setMailPerso('jane.doe@exemple.fr')
            ->setSitePerso('https://janedoe.exemple.fr')
            ->setTel1('06 12 34 56 78')
            ->setAdresseEtudiante([
                'adresse' => '12 rue de Québec',
                'complement1' => 'Résidence Jules Verne, appartement 204',
                'codePostal' => '10000',
                'ville' => 'Troyes',
            ])
            ->setAdresseParentale([
                'adresse' => '8 place Drouet d\'Erlon',
                'codePostal' => '51100',
                'ville' => 'Reims',
            ])
        ;
        $manager->persist($etudiant);

        $manager->flush();
    }
}
