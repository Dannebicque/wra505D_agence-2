<?php

namespace App\Service\Dashboard\Core;

use App\Domain\Dashboard\WidgetDataProviderInterface;
use App\Domain\Dashboard\DashboardContext;
use App\Security\UserEffectivePermissionService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class WidgetDataRegistry
{
    /**
     * @param iterable<WidgetDataProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.dashboard.widget_data_provider')]
        private readonly iterable $providers,
        private readonly WidgetRegistry $widgets,
        private readonly WidgetAccessChecker $access,
        private readonly UserEffectivePermissionService $permissions,
    ) {
    }

    /** @return array<mixed>|null */
    public function get(string $code, Personnel|Etudiant $user): ?array
    {
        $widget = $this->widgets->get($code);
        if ($widget === null) {
            return null;
        }
        if (!$this->access->isGranted($widget, $user)) {
            throw new AccessDeniedHttpException('Widget non autorisé.');
        }
        $assignment = $user instanceof Personnel ? $this->permissions->getActiveDepartementPersonnel($user) : null;
        $context = new DashboardContext($assignment?->getDepartement()?->getId(), $assignment, $this->access->getDiplomeIds($widget, $user));
        foreach ($this->providers as $provider) {
            if ($provider->supports($code)) {
                return $provider->getData($code, $user, $context);
            }
        }

        return null;
    }
}
