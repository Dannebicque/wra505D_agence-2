<?php

namespace App\EventListener\Notification;

use App\Entity\Notification\MessageEnvoye;
use App\Repository\Notification\MessageEnvoyeRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Email;

/**
 * Garde une copie de chaque e-mail parti, pour que le destinataire le retrouve dans ses
 * notifications. On écoute le mailer plutôt qu'EmailService : tout envoi est capté, y compris
 * ceux des modules qui écrivent leurs e-mails eux-mêmes.
 */
class MessageEnvoyeListener
{
    public function __construct(
        private readonly MessageEnvoyeRepository $messages,
    ) {
    }

    #[AsEventListener(event: SentMessageEvent::class)]
    public function onSent(SentMessageEvent $event): void
    {
        $email = $event->getMessage()->getOriginalMessage();
        if (!$email instanceof Email) {
            return;
        }

        $sujet = $email->getSubject() ?? '(sans objet)';
        $texte = $this->texte($email);
        foreach ([...$email->getTo(), ...$email->getCc(), ...$email->getBcc()] as $adresse) {
            $this->messages->enregistrer(new MessageEnvoye($adresse->getAddress(), $sujet, $texte));
        }
    }

    private function texte(Email $email): ?string
    {
        $enTexte = null !== $email->getTextBody();
        $corps = $email->getTextBody() ?? $email->getHtmlBody();
        if (is_resource($corps)) {
            $corps = stream_get_contents($corps);
        }
        if (!is_string($corps)) {
            return null;
        }
        if (!$enTexte) {
            $corps = html_entity_decode(strip_tags($corps));
        }

        $corps = trim((string) preg_replace('/\s+/u', ' ', $corps));

        return '' === $corps ? null : $corps;
    }
}
