<?php

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiDto\Notification\NotificationFeed;
use App\ApiDto\Notification\MarkAsRead;
use App\Entity\Users\Etudiant;
use App\Service\Notification\NotificationCenter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Marque les notifications de l'étudiant connecté comme lues et renvoie son fil à jour, pour que
 * le compteur de non lues suive sans second appel.
 *
 * @implements ProcessorInterface<MarkAsRead, NotificationFeed>
 */
final class MarkAsReadProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationCenter $center,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationFeed
    {
        $etudiant = $this->security->getUser();
        if (!$etudiant instanceof Etudiant) {
            throw new AccessDeniedHttpException('Les notifications sont réservées aux étudiants.');
        }

        $this->center->markAsRead($etudiant, $data->keys);

        return NotificationFeed::fromFeed($this->center->forStudent($etudiant));
    }
}
