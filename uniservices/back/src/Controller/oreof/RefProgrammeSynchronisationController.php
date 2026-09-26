<?php

namespace App\Controller\oreof;

use App\Security\StructureVoter;
use App\Service\OReOF\SynchroRefFormation;
use App\Utils\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/oreof/ref-formation/synchronisation', name: 'oreof_programme_synchronisation', methods: ['POST'])]
#[IsGranted(StructureVoter::CAN_EDIT_PN)]
class RefProgrammeSynchronisationController extends AbstractController
{
    public function __invoke(
        SynchroRefFormation $synchroRefFormation,
        Request               $request
    ): Response {
        $payload = $request->getPayload();
        $ids = [];
        foreach (['selectedDiplome', 'anneeUniversitaire', 'oreofId'] as $key) {
            $ids[$key] = $payload->filter($key, null, FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
            if (!is_int($ids[$key])) {
                return JsonResponse::Error('selectedDiplome, anneeUniversitaire et oreofId, entiers, sont requis.');
            }
        }
        $synchro = $synchroRefFormation->synchroniser($ids['selectedDiplome'], $ids['anneeUniversitaire'], $ids['oreofId']);

        return JsonResponse::Success('Synchronisation des compétences terminée', [
            'synchronisation' => $synchro,
        ]);
    }
}
