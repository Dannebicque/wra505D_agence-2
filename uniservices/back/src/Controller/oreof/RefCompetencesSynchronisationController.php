<?php

namespace App\Controller\oreof;

use App\Service\OReOF\SynchroRefCompetences;
use App\Utils\JsonRequest;
use App\Utils\JsonResponse;
use App\Utils\LooseValue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/oreof/ref-competences/synchronisation', name: 'oreof_competences_synchronisation')]
class RefCompetencesSynchronisationController extends AbstractController
{
    public function __invoke(
        SynchroRefCompetences $synchroRefCompetences,
        Request               $request
    ): Response {
        $data = JsonRequest::getValuesFromString($request->getContent());
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Expected a JSON object.');
        }
        $synchro = $synchroRefCompetences->synchroniser(LooseValue::int($data['departementId']), LooseValue::int($data['diplomeId']));

        return JsonResponse::Success('Synchronisation des compétences terminée', [
            'synchronisation' => $synchro,
        ]);
    }
}
