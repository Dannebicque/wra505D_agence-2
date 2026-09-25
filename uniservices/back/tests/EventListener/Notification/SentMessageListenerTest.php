<?php

namespace App\Tests\EventListener\Notification;

use App\Entity\Notification\SentMessage;
use App\EventListener\Notification\SentMessageListener;
use App\Repository\Notification\SentMessageRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\SentMessage as MailerSentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class SentMessageListenerTest extends TestCase
{
    /**
     * @return list<SentMessage>
     */
    private function enregistres(Email $email): array
    {
        $enregistres = [];
        $messages = $this->createMock(SentMessageRepository::class);
        $messages->method('save')->willReturnCallback(function (SentMessage $message) use (&$enregistres) {
            $enregistres[] = $message;
        });

        $envoi = new MailerSentMessage($email, new Envelope(new Address('intranet@iut.fr'), [new Address('x@iut.fr')]));
        (new SentMessageListener($messages))->onSent(new SentMessageEvent($envoi));

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
            array_map(fn (SentMessage $m) => $m->getRecipient(), $enregistres),
        );
        self::assertSame('Changement de salle', $enregistres[0]->getSubject());
        self::assertSame('Le TP a lieu en B204.', $enregistres[0]->getText());
    }

    public function testTireUnTexteSimpleDuHtmlQuandIlNyAPasDeVersionTexte(): void
    {
        $email = (new Email())
            ->from('intranet@iut.fr')
            ->to('jane@etudiant.univ-reims.fr')
            ->subject('Questionnaire')
            ->html('<p>Répondez <strong>avant vendredi</strong>&nbsp;!</p>');

        self::assertSame('Répondez avant vendredi !', $this->enregistres($email)[0]->getText());
    }
}
