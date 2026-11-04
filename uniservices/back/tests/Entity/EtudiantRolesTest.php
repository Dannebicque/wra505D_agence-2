<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Users\Etudiant;
use PHPUnit\Framework\TestCase;

/**
 * Les notes, l'emploi du temps complet et la liste des étudiants se protègent par
 * `not is_granted('ROLE_ETUDIANT')` : un étudiant doit garder ce rôle, sinon il les lit tous.
 */
final class EtudiantRolesTest extends TestCase
{
    public function testUnEtudiantGardeSonRole(): void
    {
        $etudiant = (new Etudiant())->setRoles(['ROLE_ETUDIANT']);

        self::assertContains('ROLE_ETUDIANT', $etudiant->getRoles());
    }
}
