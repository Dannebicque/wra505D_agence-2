<?php

declare(strict_types=1);

namespace App\Tests\AnneeUniversitaire;

use App\AnneeUniversitaire\ContexteAnnuel;
use App\AnneeUniversitaire\PerimetreAnnuelRegistry;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Une route sans politique annuelle répond 500 : chaque ressource API et chaque contrôleur métier
 * doit la déclarer.
 */
final class AnnualPolicyCoverageTest extends KernelTestCase
{
    /** Espaces de noms que `AnnualRequestSubscriber` traite comme des opérations métier. */
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

    public function testEveryProjectControllerDeclaresAnAnnualPolicy(): void
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $missing = [];
        foreach ($router->getRouteCollection() as $route) {
            $controller = $route->getDefault('_controller');
            if (!is_string($controller) || $route->getDefault('_api_resource_class') !== null) {
                continue;
            }
            [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
            if (!in_array(explode('\\', $class)[0], self::PROJECT_NAMESPACES, true) || !method_exists($class, $method)) {
                continue;
            }
            $reflection = new \ReflectionMethod($class, $method);
            if ($reflection->getAttributes(ContexteAnnuel::class) === [] && $reflection->getDeclaringClass()->getAttributes(ContexteAnnuel::class) === []) {
                $missing[] = $class.'::'.$method;
            }
        }

        self::assertSame([], array_values(array_unique($missing)));
    }
}
