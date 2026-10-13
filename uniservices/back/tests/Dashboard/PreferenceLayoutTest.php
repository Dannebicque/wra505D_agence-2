<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Domain\Dashboard\PreferenceLayout;
use App\Entity\Dashboard\DashboardPreference;
use PHPUnit\Framework\TestCase;

final class PreferenceLayoutTest extends TestCase
{
    private function preference(string $widgetKey, ?int $id = null): DashboardPreference
    {
        $preference = (new DashboardPreference())->setWidgetKey($widgetKey);
        if (null !== $id) {
            (new \ReflectionProperty(DashboardPreference::class, 'id'))->setValue($preference, $id);
        }

        return $preference;
    }

    /**
     * @param list<DashboardPreference> $preferences
     *
     * @return list<string>
     */
    private function keys(array $preferences): array
    {
        return array_map(static fn (DashboardPreference $p) => $p->getWidgetKey(), $preferences);
    }

    public function testMovesDownToTheRequestedIndex(): void
    {
        $all = static fn (DashboardPreference $p): bool => true;
        $ordered = [$this->preference('a'), $this->preference('b'), $this->preference('c'), $this->preference('d')];

        self::assertSame(['a', 'c', 'd', 'b'], $this->keys(PreferenceLayout::move($ordered, 'b', 3, $all)));
    }

    public function testMovesUpToTheRequestedIndex(): void
    {
        $all = static fn (DashboardPreference $p): bool => true;
        $ordered = [$this->preference('a'), $this->preference('b'), $this->preference('c'), $this->preference('d')];

        self::assertSame(['d', 'a', 'b', 'c'], $this->keys(PreferenceLayout::move($ordered, 'd', 0, $all)));
    }

    public function testCountsOnlyTheWidgetsTheUserSees(): void
    {
        $ordered = [$this->preference('hidden'), $this->preference('a'), $this->preference('b'), $this->preference('c')];
        $visible = static fn (DashboardPreference $p): bool => 'hidden' !== $p->getWidgetKey();

        // Sur l'écran : a, b, c. Déplacer a en dernier donne b, c, a ; « hidden » garde son rang.
        self::assertSame(['hidden', 'b', 'c', 'a'], $this->keys(PreferenceLayout::move($ordered, 'a', 2, $visible)));
        self::assertSame(['hidden', 'c', 'a', 'b'], $this->keys(PreferenceLayout::move($ordered, 'c', 0, $visible)));
    }

    public function testKeepsAnIndexBeyondTheEndInsideTheList(): void
    {
        $all = static fn (DashboardPreference $p): bool => true;
        $ordered = [$this->preference('a'), $this->preference('b')];

        self::assertSame(['b', 'a'], $this->keys(PreferenceLayout::move($ordered, 'a', 99, $all)));
    }

    public function testLeavesTheOrderAloneForAnUnknownWidget(): void
    {
        $all = static fn (DashboardPreference $p): bool => true;
        $ordered = [$this->preference('a'), $this->preference('b')];

        self::assertSame(['a', 'b'], $this->keys(PreferenceLayout::move($ordered, 'zzz', 0, $all)));
    }

    public function testKeepsTheOldestPreferenceOfEachWidget(): void
    {
        $oldNotes = $this->preference('notes', 3);
        $newNotes = $this->preference('notes', 9);
        $today = $this->preference('today', 4);
        $duplicateToday = $this->preference('today', 2);

        ['kept' => $kept, 'removed' => $removed] = PreferenceLayout::splitDuplicates([$newNotes, $oldNotes, $today, $duplicateToday]);

        self::assertSame([$oldNotes, $duplicateToday], $kept);
        self::assertSame([$newNotes, $today], $removed);
    }

    public function testRemovesNothingWithoutDuplicates(): void
    {
        ['kept' => $kept, 'removed' => $removed] = PreferenceLayout::splitDuplicates([$this->preference('a', 1), $this->preference('b', 2)]);

        self::assertCount(2, $kept);
        self::assertSame([], $removed);
    }
}
