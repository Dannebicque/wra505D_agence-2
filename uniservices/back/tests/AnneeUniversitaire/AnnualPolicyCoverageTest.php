<?php

declare(strict_types=1);

namespace App\Tests\AnneeUniversitaire;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use App\AnneeUniversitaire\ContexteAnnuel;
use App\AnneeUniversitaire\PerimetreAnnuelRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Une ressource ou un contrôleur d'API sans politique annuelle répond 500 à chaque appel : le
 * manque doit faire échouer les tests, pas l'écran.
 */
final class AnnualPolicyCoverageTest extends KernelTestCase
{
    private const PROJECT_NAMESPACES = ['App', 'IntranetBundle', 'UnifolioBundle', 'MessageBundle', 'StageBundle', 'QuestionnaireBundle', 'FinanceBundle', 'DocumentBundle', 'HelpdeskBundle'];

    public function testEveryApiResourceDeclaresAnAnnualPolicy(): void
    {
        $resources = self::getContainer()->get('api_platform.metadata.resource.name_collection_factory');
        self::assertInstanceOf(ResourceNameCollectionFactoryInterface::class, $resources);
        $registry = new PerimetreAnnuelRegistry();

        $missing = [];
        foreach ($resources->create() as $class) {
            try {
                $registry->mode($class);
            } catch (\LogicException) {
                $missing[] = $class;
            }
        }

        self::assertSame([], $missing);
    }

    public function testEveryApiControllerDeclaresAnAnnualPolicy(): void
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $missing = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!str_starts_with($route->getPath(), '/api/') || !is_string($controller)) {
                continue;
            }
            [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
            if (!in_array(explode('\\', $class)[0], self::PROJECT_NAMESPACES, true) || !method_exists($class, $method)) {
                continue;
            }
            $reflection = new \ReflectionMethod($class, $method);
            if ($reflection->getAttributes(ContexteAnnuel::class) === []
                && $reflection->getDeclaringClass()->getAttributes(ContexteAnnuel::class) === []) {
                $missing[] = $name;
            }
        }

        self::assertSame([], $missing);
    }
}
