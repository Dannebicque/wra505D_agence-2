<?php

namespace App\Tests\EventListener\Notification;

use App\Entity\Notification\MessageEnvoye;
use App\EventListener\Notification\MessageEnvoyeListener;
use App\Repository\Notification\MessageEnvoyeRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MessageEnvoyeListenerTest extends TestCase
{
    /**
     * @return list<MessageEnvoye>
     */
    private function enregistres(Email $email): array
    {
        $enregistres = [];
        $messages = $this->createMock(MessageEnvoyeRepository::class);
        $messages->method('enregistrer')->willReturnCallback(function (MessageEnvoye $message) use (&$enregistres) {
            $enregistres[] = $message;
        });

        $envoi = new SentMessage($email, new Envelope(new Address('intranet@iut.fr'), [new Address('x@iut.fr')]));
        (new MessageEnvoyeListener($messages))->onSent(new SentMessageEvent($envoi));

        return $enregistres;
    }

    public function testGardeUneCopieParDestinataire(): void
    {
        $email = (new Email())
            ->from('intranet@iut.fr')
            ->to('Jane.Doe@etudiant.univ-reims.fr')
            ->cc('paul@etudiant.univ-reims.fr')
            ->subject('Changement de salle')
            ->text('Le TP a lieu en B204.');

        $enregistres = $this->enregistres($email);

        self::assertSame(
            ['jane.doe@etudiant.univ-reims.fr', 'paul@etudiant.univ-reims.fr'],
            array_map(fn (MessageEnvoye $m) => $m->getDestinataire(), $enregistres),
        );
        self::assertSame('Changement de salle', $enregistres[0]->getSujet());
        self::assertSame('Le TP a lieu en B204.', $enregistres[0]->getTexte());
    }

    public function testTireUnTexteSimpleDuHtmlQuandIlNyAPasDeVersionTexte(): void
    {
        $email = (new Email())
            ->from('intranet@iut.fr')
            ->to('jane@etudiant.univ-reims.fr')
            ->subject('Questionnaire')
            ->html('<p>Répondez <strong>avant vendredi</strong>&nbsp;!</p>');

        self::assertSame('Répondez avant vendredi !', $this->enregistres($email)[0]->getTexte());
    }
}
