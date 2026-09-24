<?php

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\Notification\FilNotifications;
use App\Entity\Users\Etudiant;
use App\Service\Notification\CentreNotifications;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Construit le fil de notifications de l'étudiant connecté. Le personnel n'en a pas encore.
 *
 * @implements ProviderInterface<FilNotifications>
 */
final class FilNotificationsProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CentreNotifications $centre,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FilNotifications
    {
        $etudiant = $this->security->getUser();
        if (!$etudiant instanceof Etudiant) {
            throw new AccessDeniedHttpException('Les notifications sont réservées aux étudiants.');
        }

        return FilNotifications::depuis($this->centre->pour($etudiant));
    }
}
