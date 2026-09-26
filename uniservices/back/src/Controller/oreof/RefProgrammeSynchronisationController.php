<?php

namespace App\Controller\oreof;

use App\Service\OReOF\SynchroRefCompetences;
use App\Service\OReOF\SynchroRefFormation;
use App\Utils\JsonRequest;
use App\Utils\JsonResponse;
use App\Utils\LooseValue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/oreof/ref-formation/synchronisation', name: 'oreof_programme_synchronisation')]
class RefProgrammeSynchronisationController extends AbstractController
{
    public function __invoke(
        SynchroRefFormation $synchroRefFormation,
        Request               $request
    ): Response {
        $data = JsonRequest::getValuesFromString($request->getContent());
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Expected a JSON object.');
        }
        $synchro = $synchroRefFormation->synchroniser(LooseValue::int($data['selectedDiplome']), LooseValue::int($data['anneeUniversitaire']), LooseValue::int($data['oreofId']));

        return JsonResponse::Success('Synchronisation des compétences terminée', [
            'synchronisation' => $synchro,
        ]);
    }
}
