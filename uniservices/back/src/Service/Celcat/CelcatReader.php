<?php

declare(strict_types=1);

namespace App\Service\Celcat;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lecture seule de la base Celcat de l'université.
 *
 * En production, CELCAT_DSN désigne le serveur SQL Server de Celcat, accessible depuis le
 * réseau de l'université avec des identifiants fournis par la DSI. En développement, il
 * désigne une base SQLite de mêmes tables, créée par app:celcat:fausse-base : le même code
 * et les mêmes requêtes tournent dans les deux cas.
 */
final class CelcatReader implements CelcatSource
{
    private ?\PDO $connection = null;

    public function __construct(
        #[Autowire('%env(resolve:CELCAT_DSN)%')]
        private readonly string $dsn,
        #[Autowire('%env(CELCAT_USER)%')]
        private readonly string $username,
        #[Autowire('%env(CELCAT_PASSWORD)%')]
        private readonly string $password,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->dsn;
    }

    /**
     * Lundi de chaque semaine de formation, indexé par son numéro Celcat.
     *
     * @return array<int, \DateTimeImmutable>
     */
    public function readWeeks(): array
    {
        $mondays = [];
        foreach ($this->executeQuery('SELECT week_no, week_date FROM CT_WEEK_CONFIG ORDER BY week_no') as $row) {
            $mondays[(int) $row['week_no']] = new \DateTimeImmutable(substr((string) $row['week_date'], 0, 10));
        }

        return $mondays;
    }

    /**
     * Événements d'un département Celcat.
     *
     * Même requête que MyCelcat::addEvents() de l'intranet V3 : mêmes tables, mêmes
     * jointures, même tri. Deux différences : des alias, car PDO ne distingue pas quatre
     * colonnes toutes nommées resourcecode, et un paramètre lié au lieu d'une concaténation.
     *
     * @return list<array<string, mixed>>
     */
    public function readEvents(int $department): array
    {
        $sql = 'SELECT CT_EVENT.event_id AS event_id, CT_EVENT.day_of_week AS day_of_week,
                CT_EVENT.start_time AS start_time, CT_EVENT.end_time AS end_time,
                CT_EVENT.weeks AS weeks, CT_EVENT_CAT.name AS category,
                CT_VIEW_EVENT_MODULE001.resourcecode AS module_code,
                CT_VIEW_EVENT_MODULE001.resourcename AS module_name,
                CT_VIEW_EVENT_STAFF001.resourcecode AS staff_code,
                CT_VIEW_EVENT_STAFF001.resourcename AS staff_name,
                CT_VIEW_EVENT_ROOM001.resourcecode AS room_code,
                CT_VIEW_EVENT_ROOM001.resourcename AS room_name,
                CT_VIEW_EVENT_GROUP001.resourcecode AS group_code,
                CT_VIEW_EVENT_GROUP001.resourcename AS group_name,
                CT_EVENT.date_change AS date_change,
                CT_VIEW_EVENT_ROOM001.resourceweeks AS room_weeks,
                CT_EVENT.notes AS notes
            FROM CT_EVENT
            INNER JOIN CT_EVENT_CAT ON CT_EVENT_CAT.event_cat_id = CT_EVENT.event_cat_id
            LEFT JOIN CT_VIEW_EVENT_STAFF001 ON CT_VIEW_EVENT_STAFF001.eid = CT_EVENT.event_id
            INNER JOIN CT_VIEW_EVENT_GROUP001 ON CT_VIEW_EVENT_GROUP001.eid = CT_EVENT.event_id
            LEFT JOIN CT_VIEW_EVENT_MODULE001 ON CT_VIEW_EVENT_MODULE001.eid = CT_EVENT.event_id
            LEFT JOIN CT_VIEW_EVENT_ROOM001 ON CT_VIEW_EVENT_ROOM001.eid = CT_EVENT.event_id
            WHERE dept_id = :departement
            ORDER BY CT_EVENT.date_change DESC, CT_EVENT.event_id DESC';

        return $this->executeQuery($sql, ['departement' => $department]);
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function executeQuery(string $sql, array $parameters = []): array
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    private function connection(): \PDO
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('Celcat n\'est pas configuré : renseigner CELCAT_DSN.');
        }

        return $this->connection ??= new \PDO(
            $this->dsn,
            '' === $this->username ? null : $this->username,
            '' === $this->password ? null : $this->password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
    }
}
