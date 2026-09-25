<?php

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiDto\Notification\FilNotifications;
use App\ApiDto\Notification\MarquageLecture;
use App\Entity\Users\Etudiant;
use App\Service\Notification\CentreNotifications;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Marque les notifications de l'étudiant connecté comme lues et renvoie son fil à jour, pour que
 * le compteur de non lues suive sans second appel.
 *
 * @implements ProcessorInterface<MarquageLecture, FilNotifications>
 */
final class MarquageLectureProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CentreNotifications $centre,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FilNotifications
    {
        $etudiant = $this->security->getUser();
        if (!$etudiant instanceof Etudiant) {
            throw new AccessDeniedHttpException('Les notifications sont réservées aux étudiants.');
        }

        $this->centre->marquerLues($etudiant, $data->cles);

        return FilNotifications::depuis($this->centre->pour($etudiant));
    }
}
