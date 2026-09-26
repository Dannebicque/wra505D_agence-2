<?php

namespace App\Service\Edt;

use App\Entity\Edt\EdtEvent;
use App\Entity\Structure\StructureSemestre;
use IntranetBundle\Entity\Previsionnel\Previsionnel;
use Doctrine\ORM\EntityManagerInterface;

class GenereSlots
{
    private int $nbSlots = 0;
    private array $groupes = [];

    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function genereAllSlots(array $previsionnels): int
    {
        foreach ($previsionnels as $previsionnel) {
            $this->getGroupes($previsionnel);
            $this->genereSlots($previsionnel);
        }
        $this->entityManager->flush();

        return $this->nbSlots;
    }

    private function genereSlots(Previsionnel $previsionnel): void
    {
        if ($previsionnel->getProgression() !== null) {
            $progression = $previsionnel->getProgression();
            foreach ($progression->getProgression() as $semaine => $value) {
                $this->genereSlotsFromProgression($value, $semaine, $previsionnel);
            }
        }
    }

    private function genereSlotsFromProgression(string $value, int|string $semaine, Previsionnel $previsionnel): void
    {
        $creneaux = explode(' ', $value);
        foreach ($creneaux as $creneau) {
            $typeCours = substr($creneau, 0, 2);
            $numeroSeance = substr($creneau, 2);
            $nbGroupes = match ($typeCours) {
                'TD' => explode(' ', $previsionnel->getProgression()?->getGrTd()),
                'TP' => explode(' ', $previsionnel->getProgression()?->getGrTp()),
                'CM' => ['CM'],
                default => [],
            };

            foreach ($nbGroupes as $getGr) {
                $this->createEdtEvent($previsionnel, $typeCours, $semaine, $numeroSeance, $getGr);
            }

        }
    }

    private function createEdtEvent(Previsionnel $previsionnel, string $typeCours, int|string $semaine, string $numeroSeance, string $getGr): void
    {
        $edtEvent = new EdtEvent();
        $semestre = $this->getSemestre($previsionnel);
        $edtEvent->setPersonnel($previsionnel->getPersonnel());
        $edtEvent->setLibPersonnel($previsionnel->getPersonnel()?->getDisplay());
        $numeroHarpege = $previsionnel->getPersonnel()?->getNumeroHarpege();
        $edtEvent->setCodePersonnel($numeroHarpege !== null ? (string) $numeroHarpege : null);
        $edtEvent->setSemestre($semestre);
        $edtEvent->setAnneeUniversitaire($previsionnel->getAnneeUniversitaire());
        $edtEvent->setType($typeCours);
        $edtEvent->setSemaineFormation(is_int($semaine) ? $semaine : (int) $semaine);
        $edtEvent->setEnseignement($previsionnel->getEnseignement());
        $edtEvent->setLibModule($previsionnel->getEnseignement()?->getLibelle());
        $edtEvent->setCodeModule($previsionnel->getEnseignement()?->getCodeEnseignement());
        $edtEvent->setOrdreSeance((int) $numeroSeance);
        $edtEvent->setCouleur($semestre?->getAnnee()?->getCouleur());
        $groupe = $this->groupes[$semestre?->getId()][strtoupper($getGr)];
        $edtEvent->setGroupe($groupe);
        $edtEvent->setLibGroupe($groupe->getLibelle());
        $edtEvent->setCodeGroupe($groupe->getCodeApogee());

        $this->entityManager->persist($edtEvent);
        $this->nbSlots++;
    }

    /**
     * Le prévisionnel ne porte plus de semestre : il se déduit de l'enseignement, via ses UE,
     * comme le fait PrevisionnelFilter.
     */
    private function getSemestre(Previsionnel $previsionnel): ?StructureSemestre
    {
        $enseignementUe = $previsionnel->getEnseignement()?->getEnseignementUes()->first();

        return $enseignementUe ? $enseignementUe->getUe()?->getSemestre() : null;
    }

    private function getGroupes(Previsionnel $previsionnel): void
    {
        $semestre = $this->getSemestre($previsionnel);
        if ($semestre !== null && !array_key_exists($semestre->getId(), $this->groupes)) {
            $groupes = $semestre->getGroupes();
            foreach ($groupes as $groupe) {
                $this->groupes[$semestre->getId()][$groupe->getLibelle()] = $groupe;
            }
        }
    }
}
