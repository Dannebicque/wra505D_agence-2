<?php

namespace QuestionnaireBundle\ApiDto\Questionnaire\Analytics;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Utils\LooseValue;
use QuestionnaireBundle\State\Provider\Questionnaire\Analytics\QuestionnaireAnalyticsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            // Droit sur le questionnaire vérifié par le provider / processeur (QuestionnaireResolver).
            security: "is_granted('IS_PERSONNEL')",
            uriTemplate: '/questionnaires/{surveyId}/analytics',
            provider: QuestionnaireAnalyticsProvider::class,
        ),
    ]
)]
final class QuestionnaireAnalyticsDto
{
    /**
     * @param array<string, int> $responsesByDate
     * @param array<string, int> $statusCounts
     * @param list<SectionInstanceAnalyticsDto> $sections
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['questionnaire:read'])]
        public string $surveyId,
        #[Groups(['questionnaire:read'])]
        public int $totalInvited,
        #[Groups(['questionnaire:read'])]
        public int $totalResponses,
        #[Groups(['questionnaire:read'])]
        public float $completionRate,
        #[Groups(['questionnaire:read'])]
        public float $averageTimeSpent,
        #[Groups(['questionnaire:read'])]
        public array $responsesByDate,
        #[Groups(['questionnaire:read'])]
        public array $statusCounts,
        #[Groups(['questionnaire:read'])]
        public array $sections,
        /** Questionnaire anonyme : nombre minimal de réponses envoyées pour afficher le détail (0 = aucun seuil). */
        #[Groups(['questionnaire:read'])]
        public int $seuilMinReponses = 0,
        /** Faux si le seuil n'est pas atteint : le détail des réponses (sections) est alors masqué. */
        #[Groups(['questionnaire:read'])]
        public bool $seuilAtteint = true,
        #[Groups(['questionnaire:read'])]
        public string $title = '',
        #[Groups(['questionnaire:read'])]
        public ?string $openingDate = null,
        #[Groups(['questionnaire:read'])]
        public ?string $publishedAt = null,
        #[Groups(['questionnaire:read'])]
        public ?string $status = null,
        /** Vrai si la personne gère ou consulte le questionnaire ; faux pour un destinataire de la synthèse seul. */
        #[Groups(['questionnaire:read'])]
        public bool $gestion = true,
        /** Réponses purgées : statistiques issues de l'archive (figées à la date de purge). */
        #[Groups(['questionnaire:read'])]
        public ?string $archiveLe = null,
    ) {
    }

    /**
     * Reconstitue les statistiques archivées au moment de la purge (tableau produit par json_encode de ce DTO),
     * éventuellement limitées à certaines sections.
     *
     * @param array<string, mixed> $archive
     * @param list<int>|null       $sections
     */
    public static function depuisArchive(array $archive, ?array $sections, ?string $archiveLe): self
    {
        $lesSections = [];
        foreach ($archive['sections'] ?? [] as $s) {
            if ($sections !== null && !\in_array(LooseValue::castInt($s['sectionInstanceId']), $sections, true)) {
                continue;
            }
            $lesSections[] = new SectionInstanceAnalyticsDto(
                sectionInstanceId: LooseValue::castInt($s['sectionInstanceId']),
                sectionTitle: LooseValue::castString($s['sectionTitle']),
                sectionType: LooseValue::castString($s['sectionType']),
                repeatItemType: LooseValue::nullableString($s['repeatItemType'] ?? null),
                repeatItemId: LooseValue::nullableString($s['repeatItemId'] ?? null),
                questions: array_map(static fn (array $q) => new QuestionStatsDto(
                    (string) $q['questionId'],
                    (string) $q['questionLabel'],
                    (string) $q['questionType'],
                    (int) $q['totalResponses'],
                    (array) $q['stats'],
                ), $s['questions'] ?? []),
                reponses: LooseValue::castInt($s['reponses'] ?? 0),
                masquee: (bool) ($s['masquee'] ?? false),
                enseignement: LooseValue::nullableString($s['enseignement'] ?? null),
                intervenant: LooseValue::nullableString($s['intervenant'] ?? null),
            );
        }

        return new self(
            surveyId: LooseValue::castString($archive['surveyId']),
            totalInvited: LooseValue::castInt($archive['totalInvited']),
            totalResponses: LooseValue::castInt($archive['totalResponses']),
            completionRate: LooseValue::castFloat($archive['completionRate']),
            averageTimeSpent: LooseValue::castFloat($archive['averageTimeSpent']),
            responsesByDate: (array) ($archive['responsesByDate'] ?? []),
            statusCounts: (array) ($archive['statusCounts'] ?? []),
            sections: $lesSections,
            seuilMinReponses: LooseValue::castInt($archive['seuilMinReponses'] ?? 0),
            seuilAtteint: (bool) ($archive['seuilAtteint'] ?? true),
            title: LooseValue::castString($archive['title'] ?? ''),
            openingDate: LooseValue::nullableString($archive['openingDate'] ?? null),
            publishedAt: LooseValue::nullableString($archive['publishedAt'] ?? null),
            status: LooseValue::nullableString($archive['status'] ?? null),
            archiveLe: $archiveLe,
        );
    }
}
