<?php

declare(strict_types=1);

namespace App\Controller\Etudiant;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ImportApogeeEtudiantController extends AbstractController
{
    #[Route('/api/etudiants/import_apogee', methods: ['POST'])]
    #[IsGranted('CAN_EDIT_ETUDIANT_SCOLARITE')]
    public function import(Request $request): Response
    {
        // Récupérer les données JSON envoyées dans la requête
        $data = $request->toArray();

        $anneeId = $data['anneeId'] ?? null;
        $semestreId = $data['semestreId'] ?? null;
        $anneeUnivId = $data['anneeUnivId'] ?? null;

        return new Response('ok');
    }

    /** @return list<array<string, mixed>> */
    public function getEtudiantsFromApogee(): array
    {
        return [];
    }
}
