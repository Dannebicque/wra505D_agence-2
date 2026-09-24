<?php

namespace App\Service\Celcat;

use App\Entity\Edt\EdtEvent;
use App\Entity\Scolarite\ScolEnseignement;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureCalendrier;
use App\Entity\Structure\StructureGroupe;
use App\Entity\Structure\StructureSemestre;
use App\Entity\Users\Personnel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Aligne les créneaux d'une année et d'un département sur ce que dit Celcat.
 *
 * Les créneaux existants sont mis à jour plutôt que recréés : les absences y sont
 * rattachées, et la base refuse de supprimer un créneau qui en porte.
 */
final class CelcatSynchronizer
{
    public function __construct(
        private readonly CelcatSource $lecteur,
        private readonly CelcatEventConverter $convertisseur,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Reprend les semaines de Celcat dans le calendrier d'uniServices, comme
     * MyCelcat::getCalendar() de l'intranet V3. L'écran étudiant en a besoin pour passer
     * d'une semaine du calendrier à une semaine de formation.
     *
     * L'intranet V3 ajoutait des lignes à chaque appel ; on met à jour celle de chaque
     * semaine, pour qu'une relance ne crée pas de doublon.
     *
     * @return int nombre de semaines écrites
     */
    public function synchroniserCalendrier(StructureAnneeUniversitaire $annee): int
    {
        /** @var array<int, StructureCalendrier> $existantes */
        $existantes = [];
        foreach ($this->entityManager->getRepository(StructureCalendrier::class)->findBy(['anneeUniversitaire' => $annee]) as $semaine) {
            $existantes[(int) $semaine->getSemaineFormation()] = $semaine;
        }

        $lundis = $this->lecteur->lireSemaines();
        foreach ($lundis as $numero => $lundi) {
            $semaine = $existantes[$numero] ?? null;
            if (null === $semaine) {
                $semaine = (new StructureCalendrier())->setAnneeUniversitaire($annee)->setSemaineFormation($numero);
                $this->entityManager->persist($semaine);
            }
            // Numéro ISO 8601 : c'est celui que l'écran calcule côté navigateur.
            $semaine->setDateLundi(\DateTime::createFromImmutable($lundi))->setSemaineReelle((int) $lundi->format('W'));
        }

        $this->entityManager->flush();

        return \count($lundis);
    }

    public function synchroniser(StructureAnneeUniversitaire $annee, int $departement): CelcatRapport
    {
        $rapport = new CelcatRapport();
        $lundis = $this->lecteur->lireSemaines();

        $groupes = $this->indexer(StructureGroupe::class, fn (StructureGroupe $g) => $g->getCodeApogee());
        $personnels = $this->indexer(Personnel::class, fn (Personnel $p) => null === $p->getNumeroHarpege() ? null : (string) $p->getNumeroHarpege());
        $enseignements = $this->indexer(ScolEnseignement::class, fn (ScolEnseignement $e) => $e->getCodeEnseignement());

        /** @var array<string, EdtEvent> $existants */
        $existants = [];
        $creneauxExistants = $this->entityManager->getRepository(EdtEvent::class)->findBy([
            'anneeUniversitaire' => $annee,
            'departementCodeCelcat' => $departement,
        ]);
        foreach ($creneauxExistants as $creneau) {
            if (null !== $creneau->getCelcatId()) {
                $existants[$this->cle($creneau)] = $creneau;
            }
        }

        $vus = [];
        foreach ($this->lecteur->lireEvenements($departement) as $ligne) {
            foreach ($this->convertisseur->convertir($ligne, $lundis) as $slot) {
                $cle = $slot->cle();
                // Celcat trie par date de modification décroissante : la première version
                // lue d'un créneau est la plus récente, on ignore les suivantes.
                if (isset($vus[$cle])) {
                    continue;
                }
                $vus[$cle] = true;

                $creneau = $existants[$cle] ?? null;
                if (null === $creneau) {
                    $creneau = new EdtEvent();
                    $this->entityManager->persist($creneau);
                    ++$rapport->crees;
                } else {
                    ++$rapport->misAJour;
                }

                $groupe = null === $slot->codeGroupe ? null : ($groupes[$slot->codeGroupe] ?? null);
                $personnel = null === $slot->codePersonnel ? null : ($personnels[$slot->codePersonnel] ?? null);
                $enseignement = $slot->estUnCours ? ($enseignements[$slot->codeModule] ?? null) : null;

                if (null !== $slot->codeGroupe && null === $groupe) {
                    $rapport->groupesInconnus[$slot->codeGroupe] = true;
                }
                if (null !== $slot->codePersonnel && null === $personnel) {
                    $rapport->personnelsInconnus[$slot->codePersonnel] = true;
                }
                if ($slot->estUnCours && null === $enseignement) {
                    $rapport->modulesInconnus[$slot->codeModule] = true;
                }

                $this->remplir($creneau, $slot, $annee, $departement, $groupe, $personnel, $enseignement);
            }
        }

        foreach ($existants as $cle => $creneau) {
            if (isset($vus[$cle])) {
                continue;
            }
            // Un cours retiré de Celcat peut porter des absences déjà relevées : la base
            // refuse la suppression, et effacer ces relevés serait pire. On le signale.
            if ($creneau->getAbsences()->count() > 0) {
                $rapport->conserves[] = $cle;
                continue;
            }
            $this->entityManager->remove($creneau);
            ++$rapport->supprimes;
        }

        $this->entityManager->flush();

        return $rapport;
    }

    private function remplir(
        EdtEvent $creneau,
        CelcatSlot $slot,
        StructureAnneeUniversitaire $annee,
        int $departement,
        ?StructureGroupe $groupe,
        ?Personnel $personnel,
        ?ScolEnseignement $enseignement,
    ): void {
        // Un événement qui n'est pas un cours prend le type de son groupe, comme dans
        // l'intranet V3, et CM à défaut.
        $type = $slot->type ?? $groupe?->getType()->value ?? 'CM';

        $creneau
            ->setCelcatId($slot->celcatId)
            ->setDepartementCodeCelcat($departement)
            ->setAnneeUniversitaire($annee)
            ->setSemaineFormation($slot->semaine)
            ->setJour($slot->jour)
            ->setDate(\DateTime::createFromImmutable($slot->date))
            ->setDebut(\DateTime::createFromImmutable($slot->debut))
            ->setFin(\DateTime::createFromImmutable($slot->fin))
            ->setType($this->limiter($type, 20))
            ->setCodeModule($this->limiter($slot->codeModule, 20))
            ->setLibModule($this->limiter($slot->libModule, 255))
            ->setEnseignement($enseignement)
            ->setCodePersonnel($this->limiter($slot->codePersonnel, 20))
            ->setLibPersonnel($this->limiter($slot->libPersonnel, 255))
            ->setPersonnel($personnel)
            ->setSalle($this->limiter($slot->libSalle ?? $slot->codeSalle, 25) ?? '-')
            ->setCodeSalle($this->limiter($slot->codeSalle, 25))
            ->setCodeGroupe($this->limiter($slot->codeGroupe, 30))
            ->setLibGroupe($this->limiter($slot->libGroupe, 255))
            ->setGroupe($groupe)
            ->setSemestre(null === $groupe ? null : $this->semestreDuGroupe($groupe))
            ->setUpdatedEvent(null === $slot->modifieLe ? null : \DateTime::createFromImmutable($slot->modifieLe));
    }

    /**
     * Même règle que GetSemestreFromGroupe de l'intranet V3 : un groupe sans parcours et
     * rattaché à un seul semestre, sinon le semestre dont le diplôme est celui du parcours.
     */
    private function semestreDuGroupe(StructureGroupe $groupe): ?StructureSemestre
    {
        $semestres = $groupe->getSemestres();
        $parcours = $groupe->getParcours();

        if (null === $parcours && 1 === $semestres->count()) {
            $semestre = $semestres->first();

            return false === $semestre ? null : $semestre;
        }

        foreach ($semestres as $semestre) {
            $diplome = $semestre->getAnnee()?->getDiplome();
            if (null !== $parcours && null !== $diplome && $parcours->getDiplome()->contains($diplome)) {
                return $semestre;
            }
        }

        return null;
    }

    private function cle(EdtEvent $creneau): string
    {
        return $creneau->getCelcatId().'_'.$creneau->getSemaineFormation().'_'.$creneau->getJour().'_'.($creneau->getCodeGroupe() ?? '');
    }

    /**
     * Les colonnes du créneau sont courtes : un libellé Celcat trop long ferait échouer
     * toute la synchronisation pour une seule ligne.
     */
    private function limiter(?string $valeur, int $longueur): ?string
    {
        return null === $valeur ? null : mb_substr($valeur, 0, $longueur);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>       $classe
     * @param callable(T): ?string  $cle
     *
     * @return array<string, T>
     */
    private function indexer(string $classe, callable $cle): array
    {
        $index = [];
        foreach ($this->entityManager->getRepository($classe)->findAll() as $entite) {
            $valeur = $cle($entite);
            if (null !== $valeur && '' !== $valeur) {
                $index[$valeur] = $entite;
            }
        }

        return $index;
    }
}
