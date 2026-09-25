<?php

namespace App\Controller\Structure;

use App\Repository\Structure\StructureGroupeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class GroupesParSemestreController extends AbstractController
{
    public function __invoke(
        int|string|null $semestreId,
        Request $request,
        StructureGroupeRepository $repo
    ): JsonResponse {
        if (!$semestreId) {
            return new JsonResponse(['error' => 'Paramètre semestre manquant'], 400);
        }
        $groupes = $repo->findBySemestreId($semestreId);

        return $this->json($groupes, 200, [], ['groups' => ['semestre:read']]);
    }
}
