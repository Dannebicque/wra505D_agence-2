<?php

namespace App\Controller\oreof;

use App\Security\ApcVoter;
use App\Service\OReOF\SynchroRefCompetences;
use App\Utils\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/oreof/ref-competences/synchronisation', name: 'oreof_competences_synchronisation', methods: ['POST'])]
#[IsGranted(ApcVoter::CAN_EDIT_APC_REFERENTIEL)]
class RefCompetencesSynchronisationController extends AbstractController
{
    public function __invoke(
        SynchroRefCompetences $synchroRefCompetences,
        Request               $request
    ): Response {
        $payload = $request->getPayload();
        $departementId = $payload->filter('departementId', null, FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
        $diplomeId = $payload->filter('diplomeId', null, FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
        if (!is_int($departementId) || !is_int($diplomeId)) {
            return JsonResponse::Error('departementId et diplomeId, entiers, sont requis.');
        }
        $synchro = $synchroRefCompetences->synchroniser($departementId, $diplomeId);

        return JsonResponse::Success('Synchronisation des compétences terminée', [
            'synchronisation' => $synchro,
        ]);
    }
}
