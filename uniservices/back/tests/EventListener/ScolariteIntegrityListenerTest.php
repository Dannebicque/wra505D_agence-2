<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Etudiant\EtudiantScolariteSemestre;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use IntranetBundle\Commission\Service\CommissionWriteContext;
use IntranetBundle\EventListener\ScolariteIntegrityListener;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Moyenne, rang et décision d'un semestre ne s'écrivent que pendant une validation de
 * sous-commission ; une inscription sans résultat se crée librement.
 */
final class ScolariteIntegrityListenerTest extends TestCase
{
    /**
     * À l'insertion, Doctrine place chaque champ dans le changeset, même resté à null : une
     * inscription neuve sans résultat ne doit pas être prise pour une écriture de résultat.
     */
    public function testCreatesAnEnrolmentWithoutResults(): void
    {
        $this->expectNotToPerformAssertions();

        $this->flush(new CommissionWriteContext(), [
            'moyenne' => [null, null],
            'rang' => [null, null],
            'decision' => [null, null],
        ]);
    }

    public function testRefusesAResultWrittenOutsideAValidation(): void
    {
        $this->expectException(ConflictHttpException::class);

        $this->flush(new CommissionWriteContext(), [
            'moyenne' => [null, 13.42],
            'rang' => [null, null],
            'decision' => [null, null],
        ]);
    }

    public function testRefusesAResultClearedOutsideAValidation(): void
    {
        $this->expectException(ConflictHttpException::class);

        $this->flush(new CommissionWriteContext(), ['decision' => [true, null]]);
    }

    public function testAcceptsAResultWrittenDuringAValidation(): void
    {
        $this->expectNotToPerformAssertions();

        $context = new CommissionWriteContext();
        $context->run(fn () => $this->flush($context, [
            'moyenne' => [null, 13.42],
            'rang' => [null, 12],
            'decision' => [null, true],
        ]));
    }

    /**
     * @param array<string, array{mixed, mixed}> $changes
     */
    private function flush(CommissionWriteContext $context, array $changes): void
    {
        $inscription = new EtudiantScolariteSemestre();

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([$inscription]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changes);
        $unitOfWork->method('getOriginalEntityData')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $listener = new ScolariteIntegrityListener($this->createStub(Security::class), $context);
        $listener->onFlush(new OnFlushEventArgs($entityManager));
    }
}
