<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Entity\Dashboard\DashboardPreference;

/**
 * Ordre et unicité des préférences d'un tableau de bord, sans accès à la base.
 */
final class PreferenceLayout
{
    /**
     * Sépare les préférences à garder, une par widget et la plus ancienne, de celles à supprimer.
     * Des requêtes simultanées sur un tableau de bord encore sans préférence en créaient chacune un
     * jeu complet : le même widget apparaissait plusieurs fois.
     *
     * @param array<DashboardPreference> $preferences
     *
     * @return array{kept: list<DashboardPreference>, removed: list<DashboardPreference>}
     */
    public static function splitDuplicates(array $preferences): array
    {
        $kept = [];
        $removed = [];
        foreach ($preferences as $preference) {
            $key = $preference->getWidgetKey();
            $older = $kept[$key] ?? null;
            if (null === $older) {
                $kept[$key] = $preference;
            } elseif (($preference->getId() ?? PHP_INT_MAX) < ($older->getId() ?? PHP_INT_MAX)) {
                $removed[] = $older;
                $kept[$key] = $preference;
            } else {
                $removed[] = $preference;
            }
        }

        return ['kept' => array_values($kept), 'removed' => $removed];
    }

    /**
     * Place le widget à l'index voulu parmi les widgets visibles, les autres gardant leur ordre.
     * Un widget que l'utilisateur ne voit pas garde sa place : l'index vient de l'écran, qui ne
     * le compte pas. Traiter l'index comme une simple position à trier plaçait en outre le widget
     * un cran trop tôt dès qu'on le descendait : il partageait sa position avec celui qu'il devait
     * dépasser.
     *
     * @param list<DashboardPreference>        $ordered
     * @param callable(DashboardPreference): bool $isVisible
     *
     * @return list<DashboardPreference>
     */
    public static function move(array $ordered, string $widgetKey, int $index, callable $isVisible): array
    {
        $visible = array_values(array_filter($ordered, $isVisible));

        $moved = null;
        $others = [];
        foreach ($visible as $preference) {
            if ($preference->getWidgetKey() === $widgetKey) {
                $moved = $preference;
            } else {
                $others[] = $preference;
            }
        }
        if (null === $moved) {
            return $ordered;
        }

        array_splice($others, max(0, min($index, count($others))), 0, [$moved]);

        $result = [];
        foreach ($ordered as $preference) {
            // Autant de widgets visibles à remettre que de places visibles : le repli ne sert pas.
            $result[] = $isVisible($preference) ? (array_shift($others) ?? $preference) : $preference;
        }

        return $result;
    }
}
