<?php

namespace App\DataFixtures;

use App\Entity\Notification\MessageEnvoye;
use App\Entity\Users\Etudiant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Copies d'e-mails envoyés à l'étudiant de test, pour que son centre de notifications montre des
 * messages sans qu'un envoi réel ait eu lieu.
 */
class MessagesEnvoyesFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int
    {
        return 13;
    }

    public function load(ObjectManager $manager): void
    {
        $etudiant = $manager->getRepository(Etudiant::class)->findOneBy(['username' => 'etudiant']);
        $adresse = $etudiant?->getMailUniv();
        if (null === $adresse) {
            throw new \RuntimeException('Adresse universitaire de l\'étudiant de test introuvable.');
        }

        $maintenant = new \DateTimeImmutable();
        $messages = [
            ['Questionnaire : votre avis sur la rentrée', 'Bonjour, le département MMI vous invite à répondre au questionnaire de rentrée avant vendredi. Il prend cinq minutes et reste anonyme.', '-2 days'],
            ['Changement de salle pour R1.11 Développement web', 'Le TP de jeudi a lieu en salle B204 au lieu de B112. Les horaires ne changent pas.', '-5 hours'],
        ];

        foreach ($messages as [$sujet, $texte, $quand]) {
            $manager->persist(new MessageEnvoye($adresse, $sujet, $texte, $maintenant->modify($quand)));
        }

        $manager->flush();
    }
}
