<?php

namespace IntranetBundle\Services\Dashboard\Provider;

use App\Domain\Dashboard\WidgetDataProviderInterface;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Repository\Edt\EdtEventRepository;
use App\Repository\Structure\StructureDepartementRepository;
use App\Service\Notification\SemestresEtudiant;
use AuthBundle\Services\Dashboard\Provider\AuthWidgetDataProvider;

class IntranetWidgetDataProvider implements WidgetDataProviderInterface
{
    public function __construct(
        private readonly EdtEventRepository $edtEventRepository,
        private readonly StructureDepartementRepository $structureDepartementRepository,
        private readonly AuthWidgetDataProvider $authWidgetDataProvider,
        private readonly SemestresEtudiant $semestresEtudiant,
    ) {
    }

    public function supports(string $code): bool
    {
        return str_starts_with($code, 'intranet.');
    }

    public function getData(string $code, Personnel|Etudiant $user): array
    {
        return match ($code) {
            'intranet.emploi_du_temps' => $this->getEmploiDuTemps($user),
            'intranet.contacts' => $this->getContacts($user),
            'intranet.actualites' => $this->authWidgetDataProvider->getData('auth.actus_int', $user),
            'intranet.actualites_iut' => $this->authWidgetDataProvider->getData('auth.actus_ext', $user),
            'intranet.actions_urgentes' => [
                'items' => [
                    ['label' => '3 validations de stages en attente', 'priority' => 'high'],
                    ['label' => '2 conventions à signer aujourd\'hui', 'priority' => 'medium'],
                ],
            ],
            'intranet.notes' => [
                'items' => [
                    ['title' => 'Relancer alternants absents', 'done' => false],
                    ['title' => 'Préparer réunion pédagogique', 'done' => true],
                ],
            ],
            default => [],
        };
    }

    /**
     * Coordonnées de chaque département actif, et département de l'étudiant pour que le front
     * puisse le placer en premier.
     *
     * @return array{items: list<array{id: int|null, libelle: string|null, telephone: string|null, siteWeb: string|null}>, departementEtudiantId: int|null}
     */
    private function getContacts(Personnel|Etudiant $user): array
    {
        $departements = $this->structureDepartementRepository->findBy(['actif' => true], ['libelle' => 'ASC']);
        $departementEtudiant = $user instanceof Etudiant
            ? $this->structureDepartementRepository->findOneByEtudiant($user)
            : null;

        return [
            'items' => array_map(fn ($departement) => [
                'id' => $departement->getId(),
                'libelle' => $departement->getLibelle(),
                'telephone' => $departement->getTelContact(),
                'siteWeb' => $departement->getSiteWeb(),
            ], $departements),
            'departementEtudiantId' => $departementEtudiant?->getId(),
        ];
    }

    private function getEmploiDuTemps(Personnel|Etudiant $user): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $events = $user instanceof Personnel
            ? $this->edtEventRepository->findByPersonnelAndRange($user->getId(), $today, $tomorrow)
            : $this->edtEventRepository->findByGroupesAndRange($this->groupes($user), $today, $tomorrow);
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, \IntlDateFormatter::GREGORIAN, 'EEEE d MMMM yyyy');
        return [
            'todayLabel' => $formatter->format($today),
            'items' => array_map(fn ($e) => [
                'heure'  => $e->getDebut()?->format('H:i') . ' - ' . $e->getFin()?->format('H:i'),
                'groupe' => $e->getLibGroupe(),
                'cours'  => $e->getCodeModule() . ' - ' . $e->getLibModule(),
                'salle'  => $e->getSalle() ?? '-',
                'color'  => $e->getCouleur(),
                'eval'   => $e->isEvaluation(),
            ], $events),
        ];
    }

    /**
     * Les groupes de l'étudiant dans ses semestres de l'année, ceux que lit son emploi du temps.
     *
     * @return list<int>
     */
    private function groupes(Etudiant $etudiant): array
    {
        $ids = [];
        foreach ($this->semestresEtudiant->pour($etudiant) as $scolariteSemestre) {
            foreach ($scolariteSemestre->getGroupes() as $groupe) {
                $ids[] = $groupe->getId();
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
