<?php

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
    private ?\PDO $connexion = null;

    public function __construct(
        #[Autowire('%env(resolve:CELCAT_DSN)%')]
        private readonly string $dsn,
        #[Autowire('%env(CELCAT_USER)%')]
        private readonly string $utilisateur,
        #[Autowire('%env(CELCAT_PASSWORD)%')]
        private readonly string $motDePasse,
    ) {
    }

    public function estConfigure(): bool
    {
        return '' !== $this->dsn;
    }

    /**
     * Lundi de chaque semaine de formation, indexé par son numéro Celcat.
     *
     * @return array<int, \DateTimeImmutable>
     */
    public function lireSemaines(): array
    {
        $lundis = [];
        foreach ($this->executer('SELECT week_no, week_date FROM CT_WEEK_CONFIG ORDER BY week_no') as $ligne) {
            $lundis[(int) $ligne['week_no']] = new \DateTimeImmutable(substr((string) $ligne['week_date'], 0, 10));
        }

        return $lundis;
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
    public function lireEvenements(int $departement): array
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

        return $this->executer($sql, ['departement' => $departement]);
    }

    /**
     * @param array<string, int|string> $parametres
     *
     * @return list<array<string, mixed>>
     */
    private function executer(string $sql, array $parametres = []): array
    {
        $requete = $this->connexion()->prepare($sql);
        $requete->execute($parametres);

        /** @var list<array<string, mixed>> $lignes */
        $lignes = $requete->fetchAll(\PDO::FETCH_ASSOC);

        return $lignes;
    }

    private function connexion(): \PDO
    {
        if (!$this->estConfigure()) {
            throw new \LogicException('Celcat n\'est pas configuré : renseigner CELCAT_DSN.');
        }

        return $this->connexion ??= new \PDO(
            $this->dsn,
            '' === $this->utilisateur ? null : $this->utilisateur,
            '' === $this->motDePasse ? null : $this->motDePasse,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
    }
}
