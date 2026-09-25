<?php

namespace App\Service\Notification;

/**
 * Un événement qui concerne l'étudiant. La clé est stable d'un appel à l'autre : c'est elle que
 * l'on marque comme lue.
 */
final class Notification
{
    public const TYPE_NOTE = 'note';
    public const TYPE_ABSENCE = 'absence';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_ACTUALITE = 'actualite';
    public const TYPE_MESSAGE = 'message';

    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $text,
        public readonly \DateTimeImmutable $date,
        public readonly string $link,
    ) {
    }
}
