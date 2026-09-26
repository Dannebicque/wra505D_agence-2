<?php

namespace App\State\Provider\UniEdt;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiDto\PersonnelsContraintes;
use App\Entity\Structure\StructureCalendrier;
use App\Entity\Users\Personnel;
use App\Repository\PersonnelRepository;
use App\Repository\Structure\StructureCalendrierRepository;
use App\Utils\LooseValue;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<\App\ApiDto\PersonnelsContraintes> */
class PersonnelsContraintesProvider implements ProviderInterface
{
    public function __construct(
        protected StructureCalendrierRepository $structureCalendrierRepository,
        protected PersonnelRepository           $personnelRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // récupérer le lundi de la semaineFormation,
        // caluler la date du vendredi.
        // identifier toutes les contraintes qui sont dans la plage de date
        // construire le DTO qui va bien

        if ($operation instanceof CollectionOperationInterface) {

        }

        $semaine = $uriVariables['semaineFormation'];
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            throw new \LogicException('PersonnelsContraintesProvider needs the HTTP request.');
        }
        $personnel = $request->query->get('personnel');

        if ($semaine === null && $personnel === null) {
            return null;
        }

        if ($semaine !== null) {
            $semaine = $this->structureCalendrierRepository->findOneBy(['semaineFormation' => $semaine]); //todo: ajouter l'année universitaire courante
            if (null === $semaine) {
                throw new \LogicException('Semaine de formation introuvable.');
            }
        }

        if ($personnel !== null) {
            $personnel = $this->personnelRepository->find($personnel);
        }

        if (null === $semaine) {
            throw new \LogicException('Semaine de formation introuvable.');
        }

        $contraintes = new PersonnelsContraintes();
        $contraintes->setSemaineFormation($semaine);
        if ($personnel !== null) {
            $contraintes->setPersonnel($personnel);
            $contraintes->setContraintes($this->getContraintes($semaine, $personnel));
        } else {
            $contraintes->setContraintes([]); //récupérer les contraintes globales et tous les profs dans un tableau structuré ?
        }

        // Retrieve the state from somewhere
        return $contraintes;
    }

    /** @return array<string, array{type: string, contrainte: array<mixed>}> */
    private function getContraintes(StructureCalendrier $semaine, Personnel $personnel): array
    {
        //todo: si pas de semaine seule les contraintes all...

        //récupère les contraintes de la semaine pour le personnel + les contraintes de l'année pour le personnel
        $tContraintes = [];
        $contraintesPersonnels = $personnel->getContraintesEdt() ?? [];
        foreach ($contraintesPersonnels as $typeContrainte => $contraintes) {
            foreach (LooseValue::row($contraintes) as $keySemaine => $contrainte) {
                if ($keySemaine === 'all') {
                    foreach (LooseValue::rows($contrainte) as $contr) {
                        $creneau = LooseValue::castString($contr['day']) . '_' . LooseValue::castString($contr['time']);
                        $tContraintes[$creneau] = ['type' => $typeContrainte, 'contrainte' => $contr];
                    }
                }
                if ((int)$keySemaine === $semaine->getSemaineFormation()) {
                    foreach (LooseValue::rows($contrainte) as $contr) {
                        $creneau = LooseValue::castString($contr['day']) . '_' . LooseValue::castString($contr['time']);
                        $tContraintes[$creneau] = ['type' => $typeContrainte, 'contrainte' => $contr];
                    }
                }
            }
        }


        return $tContraintes;
    }
}
