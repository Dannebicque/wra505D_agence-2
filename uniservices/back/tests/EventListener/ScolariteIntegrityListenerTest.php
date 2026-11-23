<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Etudiant\EtudiantScolariteSemestre;
use App\Entity\Structure\StructureSemestre;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use IntranetBundle\Commission\Service\CommissionWriteContext;
use IntranetBundle\EventListener\ScolariteIntegrityListener;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Les résultats semestriels ne s'écrivent que pendant la validation, mais une inscription se crée
 * à tout moment.
 */
final class ScolariteIntegrityListenerTest extends TestCase
{
    /**
     * Doctrine place tous les champs d'une insertion dans son jeu de modifications, y compris les
     * résultats restés vides : l'inscription doit passer.
     */
    public function testAcceptsANewEnrolmentWithEmptyResults(): void
    {
        $inscription = new EtudiantScolariteSemestre();
        $inscription->setSemestre(new StructureSemestre());

        $this->flush($inscription, ['moyenne' => [null, null], 'rang' => [null, null], 'decision' => [null, null]]);

        $this->addToAssertionCount(1);
    }

    /**
     * Une moyenne écrite hors validation reste refusée, que l'inscription soit neuve ou non.
     */
    public function testRejectsAnAverageWrittenOutsideValidation(): void
    {
        $inscription = new EtudiantScolariteSemestre();
        $inscription->setSemestre(new StructureSemestre());

        $this->expectException(ConflictHttpException::class);

        $this->flush($inscription, ['moyenne' => [null, 12.5], 'rang' => [null, null], 'decision' => [null, null]]);
    }

    /**
     * @param array<string, array{mixed, mixed}> $changeSet
     */
    private function flush(EtudiantScolariteSemestre $inscription, array $changeSet): void
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([$inscription]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changeSet);
        $unitOfWork->method('getOriginalEntityData')->willReturn([]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(ClassMetadata::class));

        $listener = new ScolariteIntegrityListener($this->createMock(Security::class), new CommissionWriteContext());
        $listener->onFlush(new OnFlushEventArgs($entityManager));
    }
}
