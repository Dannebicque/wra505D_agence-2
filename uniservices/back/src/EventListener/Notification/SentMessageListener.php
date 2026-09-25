<?php

namespace App\EventListener\Notification;

use App\Entity\Notification\SentMessage;
use App\Repository\Notification\SentMessageRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Email;

/**
 * Garde une copie de chaque e-mail parti, pour que le destinataire le retrouve dans ses
 * notifications. On écoute le mailer plutôt qu'EmailService : tout envoi est capté, y compris
 * ceux des modules qui écrivent leurs e-mails eux-mêmes.
 */
class SentMessageListener
{
    public function __construct(
        private readonly SentMessageRepository $messages,
    ) {
    }

    #[AsEventListener(event: SentMessageEvent::class)]
    public function onSent(SentMessageEvent $event): void
    {
        $email = $event->getMessage()->getOriginalMessage();
        if (!$email instanceof Email) {
            return;
        }

        $subject = $email->getSubject() ?? '(sans objet)';
        $text = $this->text($email);
        foreach ([...$email->getTo(), ...$email->getCc(), ...$email->getBcc()] as $address) {
            $this->messages->save(new SentMessage($address->getAddress(), $subject, $text));
        }
    }

    private function text(Email $email): ?string
    {
        $isText = null !== $email->getTextBody();
        $body = $email->getTextBody() ?? $email->getHtmlBody();
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }
        if (!is_string($body)) {
            return null;
        }
        if (!$isText) {
            $body = html_entity_decode(strip_tags($body));
        }

        $body = trim((string) preg_replace('/\s+/u', ' ', $body));

        return '' === $body ? null : $body;
    }
}
