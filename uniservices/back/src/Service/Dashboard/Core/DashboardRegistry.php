<?php

namespace App\Service\Dashboard\Core;

use App\Domain\Dashboard\DashboardDefinitionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class DashboardRegistry
{
    /** @var array<string, DashboardDefinitionInterface> */
    private array $dashboards = [];

    /** @param iterable<DashboardDefinitionInterface> $definitions */
    public function __construct(
        #[AutowireIterator('app.dashboard.definition')]
        iterable $definitions
    ) {
        foreach ($definitions as $definition) {
            $this->dashboards[$definition->getCode()] = $definition;
        }
    }

    public function get(string $code): ?DashboardDefinitionInterface
    {
        return $this->dashboards[$code] ?? null;
    }

    /** @return array<string, DashboardDefinitionInterface> */
    public function all(): array
    {
        return $this->dashboards;
    }
}
