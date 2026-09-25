<?php

namespace App\ApiDto\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use Symfony\Component\Serializer\Attribute\SerializedName;
use App\Service\Notification\Notification;
use App\State\Processor\Notification\MarkAsReadProcessor;
use App\State\Provider\Notification\NotificationFeedProvider;

/**
 * Les notifications de l'étudiant connecté, messages compris. L'adresse ne porte aucun
 * identifiant : on ne peut lire et marquer que les siennes.
 */
#[ApiResource(
    shortName: 'FilNotifications',
    operations: [
        new Get(
            uriTemplate: '/me/notifications',
            uriVariables: [],
            openapi: new Operation(
                summary: 'Notes, absences, documents, actualités et messages de l\'étudiant connecté, des 90 derniers jours',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: NotificationFeedProvider::class,
        ),
        new Post(
            uriTemplate: '/me/notifications/lues',
            status: 200,
            openapi: new Operation(
                summary: 'Marque des notifications comme lues (toutes si aucune clé n\'est donnée) et renvoie le fil à jour',
            ),
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: MarkAsRead::class,
            read: false,
            processor: MarkAsReadProcessor::class,
        ),
    ],
)]
final class NotificationFeed
{
    /**
     * @param list<array{cle: string, type: string, titre: string, texte: ?string, date: string, lien: string, lue: bool}> $items
     */
    public function __construct(
        #[SerializedName('nonLues')]
        private readonly int $unreadCount,
        #[SerializedName('notifications')]
        private readonly array $items,
    ) {
    }

    /**
     * @param list<array{notification: Notification, lue: bool}> $feed
     */
    public static function fromFeed(array $feed): self
    {
        return new self(
            count(array_filter($feed, fn (array $element) => !$element['lue'])),
            array_map(fn (array $element) => [
                'cle' => $element['notification']->key,
                'type' => $element['notification']->type,
                'titre' => $element['notification']->title,
                'texte' => $element['notification']->text,
                'date' => $element['notification']->date->format(\DateTimeInterface::ATOM),
                'lien' => $element['notification']->link,
                'lue' => $element['lue'],
            ], $feed),
        );
    }

    public function getUnreadCount(): int
    {
        return $this->unreadCount;
    }

    /**
     * @return list<array{cle: string, type: string, titre: string, texte: ?string, date: string, lien: string, lue: bool}>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
