<?php

namespace App\Tests\Service\Celcat;

use App\Entity\Edt\EdtEvent;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Users\Personnel;
use App\Enum\TypeGroupeEnum;
use App\Service\Celcat\CelcatEventConverter;
use App\Service\Celcat\CelcatSource;
use App\Service\Celcat\CelcatSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use IntranetBundle\Entity\Etudiant\EtudiantAbsence;
use PHPUnit\Framework\TestCase;

final class CelcatSynchronizerTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $lignes = [];

    /** @var array<class-string, list<object>> */
    private array $enBase = [];

    /** @var list<object> */
    private array $persistes = [];

    /** @var list<object> */
    private array $supprimes = [];

    private StructureAnneeUniversitaire $annee;

    protected function setUp(): void
    {
        $this->annee = new StructureAnneeUniversitaire();
        $this->enBase = [
            EdtEvent::class => [],
            StructureGroupe::class => [$this->groupe('MMICM', TypeGroupeEnum::TYPE_GROUPE_CM), $this->groupe('MMITDAB', TypeGroupeEnum::TYPE_GROUPE_TD)],
            Personnel::class => [],
            ScolEnseignement::class => [],
        ];
    }

    private function groupe(string $code, TypeGroupeEnum $type): StructureGroupe
    {
        return (new StructureGroupe())->setLibelle($code)->setType($type)->setCodeApogee($code);
    }

    /**
     * @param array<string, mixed> $surcharge
     */
    private function ajouterLigne(array $surcharge = []): void
    {
        $this->lignes[] = array_merge([
            'event_id' => 1,
            'day_of_week' => 0,
            'start_time' => '1899-12-30 08:00:00',
            'end_time' => '1899-12-30 10:00:00',
            'weeks' => 'Y',
            'category' => '[CM]',
            'module_code' => 'R1.02',
            'module_name' => 'Culture numérique',
            'staff_code' => '10001',
            'staff_name' => 'Martin Claire',
            'room_code' => 'AMPHI',
            'room_name' => 'Amphithéâtre',
            'group_code' => 'MMICM',
            'group_name' => 'CM',
            'date_change' => '2026-09-01 12:00:00',
            'room_weeks' => null,
            'notes' => null,
        ], $surcharge);
    }

    private function creneauExistant(int $celcatId, string $codeGroupe): EdtEvent
    {
        $creneau = (new EdtEvent())
            ->setCelcatId($celcatId)
            ->setSemaineFormation(0)
            ->setJour(0)
            ->setCodeGroupe($codeGroupe)
            ->setLibModule('ancien libellé');
        $this->enBase[EdtEvent::class][] = $creneau;

        return $creneau;
    }

    private function synchroniser(): \App\Service\Celcat\CelcatRapport
    {
        $lignes = $this->lignes;
        $source = new class($lignes) implements CelcatSource {
            /** @param list<array<string, mixed>> $lignes */
            public function __construct(private readonly array $lignes)
            {
            }

            public function estConfigure(): bool
            {
                return true;
            }

            public function lireSemaines(): array
            {
                return [0 => new \DateTimeImmutable('2026-08-31'), 1 => new \DateTimeImmutable('2026-09-07')];
            }

            public function lireEvenements(int $departement): array
            {
                return $this->lignes;
            }
        };

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(function (string $classe) {
            $depot = $this->createMock(EntityRepository::class);
            $depot->method('findAll')->willReturn($this->enBase[$classe]);
            $depot->method('findBy')->willReturn($this->enBase[$classe]);

            return $depot;
        });
        $entityManager->method('persist')->willReturnCallback(function (object $entite): void {
            $this->persistes[] = $entite;
        });
        $entityManager->method('remove')->willReturnCallback(function (object $entite): void {
            $this->supprimes[] = $entite;
        });

        return (new CelcatSynchronizer($source, new CelcatEventConverter(), $entityManager))->synchroniser($this->annee, 123);
    }

    public function testCreeUnCreneauParSemaineEtParGroupe(): void
    {
        $this->ajouterLigne(['weeks' => 'YY', 'group_code' => 'MMICM']);
        $this->ajouterLigne(['weeks' => 'YY', 'group_code' => 'MMITDAB']);

        $rapport = $this->synchroniser();

        self::assertSame(4, $rapport->crees);
        self::assertCount(4, $this->persistes);
    }

    public function testMetAJourUnCreneauExistantSansLeRecreer(): void
    {
        $existant = $this->creneauExistant(1, 'MMICM');
        $this->ajouterLigne(['module_name' => 'Nouveau libellé']);

        $rapport = $this->synchroniser();

        self::assertSame(0, $rapport->crees);
        self::assertSame(1, $rapport->misAJour);
        self::assertSame([], $this->persistes);
        self::assertSame('Nouveau libellé', $existant->getLibModule());
    }

    public function testSupprimeUnCreneauDisparuDeCelcat(): void
    {
        $disparu = $this->creneauExistant(99, 'MMICM');

        $rapport = $this->synchroniser();

        self::assertSame(1, $rapport->supprimes);
        self::assertSame([$disparu], $this->supprimes);
    }

    public function testConserveUnCreneauDisparuQuiPorteUneAbsence(): void
    {
        $this->creneauExistant(99, 'MMICM')->addAbsence(new EtudiantAbsence());

        $rapport = $this->synchroniser();

        self::assertSame(0, $rapport->supprimes);
        self::assertSame([], $this->supprimes);
        self::assertSame(['99_0_0_MMICM'], $rapport->conserves);
    }

    public function testRattacheAuGroupeEtSignaleLesCodesInconnus(): void
    {
        $this->ajouterLigne(['group_code' => 'MMITDAB']);
        $this->ajouterLigne(['event_id' => 2, 'group_code' => 'INCONNU']);

        $rapport = $this->synchroniser();

        /** @var EdtEvent $creneau */
        $creneau = $this->persistes[0];
        self::assertSame('MMITDAB', $creneau->getGroupe()?->getCodeApogee());
        self::assertArrayHasKey('INCONNU', $rapport->groupesInconnus);
        self::assertArrayHasKey('10001', $rapport->personnelsInconnus);
        self::assertArrayHasKey('R1.02', $rapport->modulesInconnus);
    }

    public function testUnHorsCoursPrendLeTypeDeSonGroupeEtCmADefaut(): void
    {
        $this->ajouterLigne(['module_code' => null, 'category' => 'Réunion', 'group_code' => 'MMITDAB']);
        $this->ajouterLigne(['event_id' => 2, 'module_code' => null, 'category' => 'Réunion', 'group_code' => 'INCONNU']);

        $this->synchroniser();

        /** @var list<EdtEvent> $creneaux */
        $creneaux = $this->persistes;
        self::assertSame('TD', $creneaux[0]->getType());
        self::assertSame('CM', $creneaux[1]->getType());
    }

    public function testGardeLaVersionLaPlusRecenteDUnCreneauEnDouble(): void
    {
        // Celcat trie par date de modification décroissante : la première ligne est la plus récente.
        $this->ajouterLigne(['module_name' => 'Version récente']);
        $this->ajouterLigne(['module_name' => 'Version ancienne']);

        $rapport = $this->synchroniser();

        /** @var EdtEvent $creneau */
        $creneau = $this->persistes[0];
        self::assertSame(1, $rapport->crees);
        self::assertSame('Version récente', $creneau->getLibModule());
    }

    public function testLimiteUnLibelleDeSalleTropLongPourSaColonne(): void
    {
        $this->ajouterLigne(['room_name' => str_repeat('Salle très longue ', 5)]);

        $this->synchroniser();

        /** @var EdtEvent $creneau */
        $creneau = $this->persistes[0];
        self::assertSame(25, mb_strlen($creneau->getSalle()));
    }
}
