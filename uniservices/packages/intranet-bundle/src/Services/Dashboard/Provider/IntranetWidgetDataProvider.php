<?php

namespace IntranetBundle\Services\Dashboard\Provider;

use App\Domain\Dashboard\WidgetDataProviderInterface;
use App\Domain\Dashboard\DashboardContext;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use IntranetBundle\Service\Dashboard\IntranetScheduleService;
use App\Repository\Structure\StructureDepartementRepository;
use AuthBundle\Services\Dashboard\Provider\AuthWidgetDataProvider;

class IntranetWidgetDataProvider implements WidgetDataProviderInterface
{
    public function __construct(
        private readonly IntranetScheduleService $schedule,
        private readonly StructureDepartementRepository $structureDepartementRepository,
        private readonly AuthWidgetDataProvider $authWidgetDataProvider,
    ) {
    }

    public function supports(string $code): bool
    {
        return str_starts_with($code, 'intranet.');
    }

    /** @return array<mixed> */
    public function getData(string $code, Personnel|Etudiant $user, ?DashboardContext $context = null): array
    {
        return match ($code) {
            'intranet.emploi_du_temps' => $this->schedule->getToday($user),
            'intranet.contacts' => $this->getContacts($user),
            'intranet.actualites' => $this->authWidgetDataProvider->getData('auth.actus_int', $user),
            'intranet.actualites_iut' => $this->authWidgetDataProvider->getData('auth.actus_ext', $user),
            'intranet.actions_urgentes' => [
                'items' => [
                    ['label' => '3 validations de stages en attente', 'priority' => 'high'],
                    ['label' => '2 conventions à signer aujourd\'hui', 'priority' => 'medium'],
                ],
            ],
            'intranet.listings_etudiants' => [
                'supportedFormats' => ['pdf', 'xlsx', 'csv'],
                'hasGotenberg' => true,
                'hasSpreadsheet' => true,
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

}
