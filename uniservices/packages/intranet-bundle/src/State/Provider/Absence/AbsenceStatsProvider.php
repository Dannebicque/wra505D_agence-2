<?php

namespace IntranetBundle\State\Provider\Absence;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Utils\LooseValue;

/** @implements ProviderInterface<object> */
class AbsenceStatsProvider implements ProviderInterface
{
    public function __construct(
        private AbsenceEpisodeProvider $absenceEpisodeProvider,
    ) {
    }

    /**
     * Renvoie des tableaux, qu'API Platform sérialise tels quels, et non des ressources.
     *
     * @return array<mixed>|object|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $episodesResult = $this->absenceEpisodeProvider->provide($operation, $uriVariables, $context);
            if ($episodesResult === null) {
                $episodes = [];
            } elseif (is_array($episodesResult)) {
                $episodes = $episodesResult;
            } elseif ($episodesResult instanceof \Traversable) {
                $episodes = iterator_to_array($episodesResult);
            } else {
                $episodes = [];
            }
            $episodes = LooseValue::rows($episodes);

            $stats = [
                'total' => [
                    'title' => 'Total de périodes d\'absence',
                    'icon' => 'pi pi-list',
                    'color' => 'yellow-500',
                    'value' => count($episodes),
                ],
                'justifiee' => [
                    'title' => 'Justifiées',
                    'icon' => 'pi pi-check',
                    'color' => 'green-500',
                    'value' => count(array_filter($episodes, fn (array $episode) => (bool)($episode['justifiee'] ?? false))),
                ],
                'non_justifiee' => [
                    'title' => 'Non justifiées',
                    'icon' => 'pi pi-times',
                    'color' => 'red-500',
                    'value' => count(array_filter($episodes, fn (array $episode) => !($episode['justifiee'] ?? false))),
                ],
                'scolarite_semestre' => [
                    'title' => 'Étudiants concernés',
                    'icon' => 'pi pi-calendar',
                    'color' => 'blue-500',
                    'value' => count(
                        array_unique(
                            array_filter(
                                array_map(fn (array $episode) => LooseValue::nullableInt($episode['scolariteSemestreId'] ?? null), $episodes),
                                fn ($id) => $id !== null
                            )
                        )
                    ),
                ],
            ];

            return $stats;
        }

        return null;
    }
}
