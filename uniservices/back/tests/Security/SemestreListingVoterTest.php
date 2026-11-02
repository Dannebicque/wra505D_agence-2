<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Structure\StructureAnnee;
use App\Entity\Structure\StructureDepartement;
use App\Entity\Structure\StructureDepartementPersonnel;
use App\Entity\Structure\StructureDiplome;
use App\Entity\Structure\StructurePn;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Repository\Structure\StructureDepartementPersonnelRepository;
use App\Security\SemestreListingVoter;
use App\Security\UserEffectivePermissionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Vérifie que les listings d'un semestre ne sortent que pour le personnel de son département. */
final class SemestreListingVoterTest extends TestCase
{
    private StructureDepartement $departement;
    private StructureSemestre $semestre;

    protected function setUp(): void
    {
        $this->departement = new StructureDepartement();
        $pn = new StructurePn((new StructureDiplome())->setDepartement($this->departement));
        $this->semestre = (new StructureSemestre())->setAnnee((new StructureAnnee())->setPn($pn));
    }

    /** Le personnel affecté au département du semestre peut exporter. */
    public function testAccordeAuPersonnelDuDepartement(): void
    {
        $personnel = new Personnel();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($personnel, affecte: true));
    }

    /** Le personnel d'un autre département ne peut pas exporter. */
    public function testRefuseAuPersonnelDUnAutreDepartement(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new Personnel(), affecte: false));
    }

    /** Le super-administrateur exporte partout. */
    public function testAccordeAuSuperAdmin(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(new Personnel(), affecte: false, superAdmin: true));
    }

    /** Un étudiant ne peut jamais exporter. */
    public function testRefuseAUnEtudiant(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new Etudiant(), affecte: true));
    }

    private function vote(UserInterface $user, bool $affecte, bool $superAdmin = false): int
    {
        $repository = $this->createMock(StructureDepartementPersonnelRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteres): ?StructureDepartementPersonnel => $affecte && $criteres['departement'] === $this->departement && $criteres['personnel'] === $user
                ? new StructureDepartementPersonnel()
                : null
        );
        $permissions = $this->createMock(UserEffectivePermissionService::class);
        $permissions->method('isSuperAdmin')->willReturn($superAdmin);

        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        return (new SemestreListingVoter($repository, $permissions))
            ->vote($token, $this->semestre, [SemestreListingVoter::CAN_EXPORT_LISTING]);
    }
}
