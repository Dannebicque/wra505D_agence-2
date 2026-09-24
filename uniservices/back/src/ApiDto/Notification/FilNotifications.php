<?php

namespace App\ApiDto\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Service\Notification\Notification;
use App\State\Processor\Notification\MarquageLectureProcessor;
use App\State\Provider\Notification\FilNotificationsProvider;

/**
 * Les notifications de l'étudiant connecté, messages compris. L'adresse ne porte aucun
 * identifiant : on ne peut lire et marquer que les siennes.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/me/notifications',
            uriVariables: [],
            openapi: new Operation(
                summary: 'Notes, absences, documents, actualités et messages de l\'étudiant connecté, des 90 derniers jours',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: FilNotificationsProvider::class,
        ),
        new Post(
            uriTemplate: '/me/notifications/lues',
            status: 200,
            openapi: new Operation(
                summary: 'Marque des notifications comme lues (toutes si aucune clé n\'est donnée) et renvoie le fil à jour',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: MarquageLecture::class,
            read: false,
            processor: MarquageLectureProcessor::class,
        ),
    ],
)]
final class FilNotifications
{
    /**
     * @param list<array{cle: string, type: string, titre: string, texte: ?string, date: string, lien: string, lue: bool}> $notifications
     */
    public function __construct(
        private readonly int $nonLues,
        private readonly array $notifications,
    ) {
    }

    /**
     * @param list<array{notification: Notification, lue: bool}> $fil
     */
    public static function depuis(array $fil): self
    {
        return new self(
            count(array_filter($fil, fn (array $element) => !$element['lue'])),
            array_map(fn (array $element) => [
                'cle' => $element['notification']->cle,
                'type' => $element['notification']->type,
                'titre' => $element['notification']->titre,
                'texte' => $element['notification']->texte,
                'date' => $element['notification']->date->format(\DateTimeInterface::ATOM),
                'lien' => $element['notification']->lien,
                'lue' => $element['lue'],
            ], $fil),
        );
    }

    public function getNonLues(): int
    {
        return $this->nonLues;
    }

    /**
     * @return list<array{cle: string, type: string, titre: string, texte: ?string, date: string, lien: string, lue: bool}>
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }
}
