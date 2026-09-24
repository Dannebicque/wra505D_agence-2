<?php

namespace App\ApiDto\Notification;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/me/notifications/lues. Sans clé, tout le fil est marqué comme lu.
 */
final class MarquageLecture
{
    /**
     * @var list<string>|null
     */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 100)])]
    public ?array $cles = null;
}
