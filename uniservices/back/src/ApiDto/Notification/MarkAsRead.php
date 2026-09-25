<?php

namespace App\ApiDto\Notification;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Corps de POST /api/me/notifications/lues. Sans clé, tout le fil est marqué comme lu.
 */
#[ApiResource(shortName: 'MarquageLecture', operations: [])]
final class MarkAsRead
{
    /**
     * @var list<string>|null
     */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 100)])]
    #[SerializedName('cles')]
    public ?array $keys = null;
}
