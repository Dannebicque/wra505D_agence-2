<?php

namespace App\EventListener;

use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof Etudiant && !$user instanceof Personnel) {
            return;
        }
        $payload = $event->getData();

        if ($user instanceof Etudiant) {
            $type = 'etudiants';
        } else {
            $type = 'personnels';
        }

        // Ajoutez l'ID de l'utilisateur au payload
        $payload['userId'] = $user->getId()??0;
        $payload['type'] = $type;

        $event->setData($payload);
    }
}
