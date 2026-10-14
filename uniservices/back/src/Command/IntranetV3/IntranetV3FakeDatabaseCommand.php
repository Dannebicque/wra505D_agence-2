<?php

declare(strict_types=1);

namespace App\Command\IntranetV3;

use App\Utils\LooseValue;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Remplit la base de la connexion `copy` avec une fausse base de l'intranet V3, pour exécuter les
 * migrateurs de app:migrate-intranet-v3 sans accès à la vraie.
 *
 * Le schéma est celui de la V3, généré depuis son mapping (DataFixtures/IntranetV3/schema.sql).
 * Le jeu de données est petit mais couvre chaque branche des migrateurs : année active (2026,
 * première année V4) et année passée, BUT en APC et licence en matières, ressource parente et
 * enfant, évaluation parente, étudiant diplômé, absence rattachée à son créneau, stage, documents.
 * Seules les colonnes lues par les migrateurs sont écrites : les autres colonnes obligatoires
 * reçoivent une valeur neutre selon leur type.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
#[AsCommand(
    name: 'app:intranet-v3:fausse-base',
    description: 'Crée une fausse base de l\'intranet V3 pour exécuter la migration',
)]
final class IntranetV3FakeDatabaseCommand extends Command
{
    /** Garde-fou : la connexion `copy` pointe en production sur la vraie V3, qu'on viderait. */
    private const DATABASE_MARKER = 'fausse';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.copy_connection')]
        private readonly Connection $source,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $database = (string) $this->source->getDatabase();
        if (!str_contains($database, self::DATABASE_MARKER)) {
            $io->error(sprintf(
                'La base de DATABASE_URL_ORIGINAL (%s) doit contenir « %s » dans son nom : cette commande la vide.',
                $database,
                self::DATABASE_MARKER,
            ));

            return Command::FAILURE;
        }

        $this->source->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $this->source->fetchFirstColumn('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        foreach ($tables as $table) {
            $this->source->executeStatement(sprintf('DROP TABLE `%s`', LooseValue::castString($table)));
        }
        foreach ($this->schemaStatements() as $statement) {
            $this->source->executeStatement($statement);
        }

        $count = 0;
        foreach ($this->dataset() as $table => $rows) {
            $required = $this->requiredColumns($table);
            foreach ($rows as $row) {
                $this->source->insert($table, $row + $this->neutralValues($required, $row));
                ++$count;
            }
        }
        $this->source->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $documents = $this->writeDocumentFiles();

        $io->success(sprintf('Fausse base V3 créée dans %s : %d lignes, %d fichiers de documents.', $database, $count, $documents));
        $io->text([
            'Migrer ensuite, de préférence dans une base cible jetable :',
            '  php bin/console app:migrate-intranet-v3',
            '  php bin/console app:migrate-intranet-v3-document --files-source=var/intranet-v3/public/upload/documents --files-target=var/documents',
            '  php bin/console app:migrate-intranet-v3-stage',
        ]);

        return Command::SUCCESS;
    }

    /** @return list<string> */
    private function schemaStatements(): array
    {
        $sql = (string) file_get_contents($this->projectDir . '/src/DataFixtures/IntranetV3/schema.sql');
        $sql = (string) preg_replace('/^--.*$/m', '', $sql);

        return array_values(array_filter(array_map(trim(...), explode(";\n", $sql)), static fn (string $statement): bool => '' !== $statement));
    }

    /**
     * Colonnes NOT NULL sans valeur par défaut, avec leur type.
     *
     * @return array<string, array{type: string, json: bool}>
     */
    private function requiredColumns(string $table): array
    {
        $columns = $this->source->fetchAllAssociative(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND IS_NULLABLE = \'NO\'
               AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE \'%auto_increment%\'',
            ['table' => $table],
        );

        $required = [];
        foreach ($columns as $column) {
            $required[LooseValue::castString($column['COLUMN_NAME'])] = [
                'type' => LooseValue::castString($column['DATA_TYPE']),
                'json' => str_contains(LooseValue::castString($column['COLUMN_COMMENT']), 'json'),
            ];
        }

        return $required;
    }

    /**
     * @param array<string, array{type: string, json: bool}> $required
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function neutralValues(array $required, array $row): array
    {
        $values = [];
        foreach (array_diff_key($required, $row) as $name => $column) {
            $values[$name] = match (true) {
                $column['json'] => '[]',
                'binary' === $column['type'] => random_bytes(16),
                in_array($column['type'], ['datetime', 'date', 'time'], true) => '2026-09-01 00:00:00',
                in_array($column['type'], ['int', 'tinyint', 'smallint', 'bigint', 'double', 'float', 'decimal'], true) => 0,
                default => '',
            };
        }

        return $values;
    }

    /**
     * Les documents V3 vivent dans public/upload/documents/ : c'est ce dossier que
     * app:migrate-intranet-v3-document reçoit en --files-source.
     */
    private function writeDocumentFiles(): int
    {
        $directory = $this->projectDir . '/var/intranet-v3/public/upload/documents';
        $this->filesystem->remove($directory);
        $count = 0;
        foreach ($this->dataset()['document'] as $document) {
            $this->filesystem->dumpFile(
                $directory . '/' . LooseValue::castString($document['document_name']),
                sprintf("Document d'exemple de la fausse base V3 : %s\n", LooseValue::castString($document['libelle'])),
            );
            ++$count;
        }

        return $count;
    }

    /**
     * Les lignes, table par table, dans l'ordre des clés étrangères.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function dataset(): array
    {
        return [
            'annee_universitaire' => [
                ['id' => 1, 'libelle' => '2025-2026', 'annee' => 2025, 'active' => 0, 'commentaire' => 'Dernière année de la V3'],
                ['id' => 2, 'libelle' => '2026-2027', 'annee' => 2026, 'active' => 1],
            ],
            'type_diplome' => [
                ['id' => 1, 'libelle' => 'Bachelor Universitaire de Technologie', 'sigle' => 'BUT', 'nb_semestres' => 6, 'niveau_entree' => 4, 'niveau_sortie' => 6, 'apc' => 1],
                ['id' => 2, 'libelle' => 'Licence professionnelle', 'sigle' => 'LP', 'nb_semestres' => 2, 'niveau_entree' => 6, 'niveau_sortie' => 6, 'apc' => 0],
            ],
            'personnel' => [
                $this->staff(1, 'annebicque', 'David', 'Annebicque', 'MCF', 'DA'),
                $this->staff(2, 'herolt', 'Cyndel', 'Herolt', 'PRAG', 'CH'),
                $this->staff(3, 'garnier', 'Thomas', 'Garnier', 'vacataire', 'TG'),
                ['deleted' => 1] + $this->staff(4, 'ancien', 'Michel', 'Ancien', 'MCF', 'MA'),
            ],
            'departement' => [
                ['id' => 1, 'libelle' => 'Métiers du Multimédia et de l\'Internet', 'logo_name' => 'mmi.png', 'tel_contact' => '03 25 42 71 00', 'couleur' => '#4D3677', 'site_web' => 'https://mmi-troyes.fr', 'description' => 'BUT MMI et licence web', 'actif' => 1, 'opt_materiel' => 1, 'opt_edt' => 1, 'opt_stage' => 1, 'respri_id' => 1],
            ],
            'personnel_departement' => [
                ['id' => 1, 'personnel_id' => 1, 'departement_id' => 1, 'annee' => 2026, 'roles' => '["ROLE_CDD","ROLE_PERMANENT"]', 'defaut' => 1],
                ['id' => 2, 'personnel_id' => 2, 'departement_id' => 1, 'annee' => 2026, 'roles' => '["ROLE_PERMANENT","ROLE_NOTES"]', 'defaut' => 1],
                ['id' => 3, 'personnel_id' => 3, 'departement_id' => 1, 'annee' => 2026, 'roles' => '[]', 'defaut' => 1],
            ],
            'apc_referentiel' => [
                ['id' => 1, 'type_diplome_id' => 1, 'departement_id' => 1, 'libelle' => 'BUT Métiers du Multimédia et de l\'Internet', 'description' => 'Référentiel national 2022', 'annee_publication' => 2022, 'type_structure' => 'BUT'],
            ],
            'apc_competence' => [
                ['id' => 1, 'apc_referentiel_id' => 1, 'libelle' => 'Comprendre les écosystèmes, les besoins des utilisateurs et les dispositifs de communication numérique', 'nom_court' => 'Comprendre', 'couleur' => 'c1'],
                ['id' => 2, 'apc_referentiel_id' => 1, 'libelle' => 'Développer pour le web et les médias numériques', 'nom_court' => 'Développer', 'couleur' => 'c4'],
            ],
            'apc_composante_essentielle' => [
                ['id' => 1, 'competence_id' => 1, 'libelle' => 'En identifiant les usages des cibles'],
                ['id' => 2, 'competence_id' => 2, 'libelle' => 'En produisant un code lisible'],
            ],
            'apc_situation_professionnelle' => [
                ['id' => 1, 'competence_id' => 1, 'libelle' => 'Audit d\'un dispositif de communication'],
            ],
            'apc_niveau' => [
                ['id' => 1, 'competence_id' => 1, 'annee_id' => 1, 'libelle' => 'Comprendre les éléments de communication', 'ordre' => 1, 'ordre_annee' => 1],
                ['id' => 2, 'competence_id' => 2, 'annee_id' => 1, 'libelle' => 'Développer un site web simple', 'ordre' => 1, 'ordre_annee' => 1],
                ['id' => 3, 'competence_id' => 2, 'annee_id' => 2, 'libelle' => 'Développer une application web', 'ordre' => 2, 'ordre_annee' => 2],
            ],
            'apc_apprentissage_critique' => [
                ['id' => 1, 'niveau_id' => 1, 'libelle' => 'Identifier les marqueurs d\'un dispositif de communication', 'code' => 'AC11.01'],
                ['id' => 2, 'niveau_id' => 1, 'libelle' => 'Produire une analyse de la concurrence', 'code' => 'AC11.02'],
                ['id' => 3, 'niveau_id' => 2, 'libelle' => 'Produire des pages web statiques', 'code' => 'AC14.01'],
                ['id' => 4, 'niveau_id' => 3, 'libelle' => 'Développer une application dynamique', 'code' => 'AC24.01'],
            ],
            'apc_parcours' => [
                ['id' => 1, 'apc_referentiel_id' => 1, 'libelle' => 'Développement web et dispositifs interactifs', 'code' => 'DWDI', 'actif' => 1, 'couleur' => 'violet', 'formation_continue' => 0],
                ['id' => 2, 'apc_referentiel_id' => 1, 'libelle' => 'Création numérique', 'code' => 'CN', 'actif' => 1, 'couleur' => 'jaune', 'formation_continue' => 0],
            ],
            'apc_parcours_niveau' => [
                ['id' => 1, 'parcours_id' => 1, 'niveau_id' => 1],
                ['id' => 2, 'parcours_id' => 1, 'niveau_id' => 2],
                ['id' => 3, 'parcours_id' => 1, 'niveau_id' => 3],
                ['id' => 4, 'parcours_id' => 2, 'niveau_id' => 1],
                ['id' => 5, 'parcours_id' => 2, 'niveau_id' => 2],
            ],
            'diplome' => [
                ['referentiel_id' => 1] + $this->diploma(1, 'BUT Métiers du Multimédia et de l\'Internet', 'MMI', 1),
                $this->diploma(2, 'Licence professionnelle Développement web', 'LP DW', 2),
            ],
            'annee' => [
                ['id' => 1, 'diplome_id' => 1, 'libelle' => 'BUT 1', 'libelle_long' => 'Première année de BUT MMI', 'ordre' => 1, 'code_etape' => 'MMI1', 'code_version' => '1', 'actif' => 1, 'couleur' => 'violet', 'opt_alternance' => 0],
                ['id' => 2, 'diplome_id' => 1, 'libelle' => 'BUT 2', 'libelle_long' => 'Deuxième année de BUT MMI', 'ordre' => 2, 'code_etape' => 'MMI2', 'code_version' => '1', 'actif' => 1, 'couleur' => 'violet', 'opt_alternance' => 1],
                ['id' => 3, 'diplome_id' => 2, 'libelle' => 'LP', 'libelle_long' => 'Licence professionnelle', 'ordre' => 3, 'code_etape' => 'LPDW', 'code_version' => '1', 'actif' => 1, 'couleur' => 'bleu', 'opt_alternance' => 1],
            ],
            'semestre' => [
                $this->semester(1, 1, 'S1', 1, 1, 'MMIS1'),
                $this->semester(2, 1, 'S2', 2, 2, 'MMIS2'),
                $this->semester(3, 2, 'S3', 1, 3, 'MMIS3'),
                $this->semester(4, 3, 'S5 LP', 1, 5, 'LPS5'),
            ],
            'ue' => [
                ['id' => 1, 'semestre_id' => 1, 'libelle' => 'UE 1.1 Comprendre', 'numero_ue' => 1, 'coefficient' => 1, 'nb_ects' => 15, 'actif' => 1, 'bonification' => 0, 'code_element' => 'MMIU11', 'apc_competence_id' => 1],
                ['id' => 2, 'semestre_id' => 1, 'libelle' => 'UE 1.4 Développer', 'numero_ue' => 2, 'coefficient' => 1, 'nb_ects' => 15, 'actif' => 1, 'bonification' => 0, 'code_element' => 'MMIU14', 'apc_competence_id' => 2],
                ['id' => 3, 'semestre_id' => 4, 'libelle' => 'UE 1 Développement', 'numero_ue' => 1, 'coefficient' => 1, 'nb_ects' => 30, 'actif' => 1, 'bonification' => 0, 'code_element' => 'LPU1'],
            ],
            'matiere' => [
                $this->subject(1, 3, 'Développement back', 'Dév. back', 'LP01', 'LPM01', 2),
                $this->subject(2, 3, 'Projet tuteuré', 'Projet', 'LP02', 'LPM02', 1),
            ],
            'apc_ressource' => [
                ['ressource_parent' => 0] + $this->resource(1, 'Anglais', 'R1.01', 'MMIR101'),
                ['ressource_parent' => 1] + $this->resource(2, 'Développement web', 'R1.11', 'MMIR111'),
                ['ressource_parent' => 0] + $this->resource(3, 'Développement web : intégration', 'R1.11a', 'MMIR111A'),
            ],
            'apc_ressource_semestre' => [
                ['apc_ressource_id' => 1, 'semestre_id' => 1],
                ['apc_ressource_id' => 2, 'semestre_id' => 1],
                ['apc_ressource_id' => 3, 'semestre_id' => 1],
            ],
            'apc_ressource_enfants' => [
                ['id' => 1, 'apc_ressource_parent_id' => 2, 'apc_ressource_enfant_id' => 3],
            ],
            'apc_ressource_competence' => [
                ['id' => 1, 'ressource_id' => 1, 'competence_id' => 1, 'coefficient' => 3],
                ['id' => 2, 'ressource_id' => 2, 'competence_id' => 2, 'parcours_id' => 1, 'coefficient' => 5],
                ['id' => 3, 'ressource_id' => 3, 'competence_id' => 2, 'coefficient' => 2],
            ],
            'apc_ressource_apprentissage_critique' => [
                ['id' => 1, 'ressource_id' => 1, 'apprentissage_critique_id' => 1],
                ['id' => 2, 'ressource_id' => 2, 'apprentissage_critique_id' => 3],
            ],
            'apc_sae' => [
                [
                    'id' => 1, 'libelle' => 'Recommandation de communication numérique', 'libelle_court' => 'Recommandation', 'description' => 'Auditer puis recommander',
                    'cm_ppn' => 0, 'td_ppn' => 10, 'tp_ppn' => 10, 'cm_formation' => 0, 'td_formation' => 10, 'tp_formation' => 10,
                    'code_matiere' => 'SAE1.01', 'code_element' => 'MMIS101', 'nb_notes' => 2, 'suspendu' => 0, 'mutualisee' => 0,
                    'projet_ppn' => 20, 'projet_formation' => 20, 'livrables' => 'Dossier de recommandation', 'exemples' => 'Audit d\'un site', 'bonification' => 0, 'objectifs' => 'Comprendre un besoin',
                ],
            ],
            'apc_sae_semestre' => [
                ['apc_sae_id' => 1, 'semestre_id' => 1],
            ],
            'apc_sae_competence' => [
                ['id' => 1, 'sae_id' => 1, 'competence_id' => 1, 'coefficient' => 4],
            ],
            'apc_sae_apprentissage_critique' => [
                ['id' => 1, 'sae_id' => 1, 'apprentissage_critique_id' => 2],
            ],
            'type_groupe' => [
                ['id' => 1, 'diplome_id' => 1, 'semestre_id' => 1, 'libelle' => 'CM', 'type' => 'CM', 'defaut' => 1, 'mutualise' => 1],
                ['id' => 2, 'diplome_id' => 1, 'semestre_id' => 1, 'libelle' => 'TD', 'type' => 'TD', 'defaut' => 0, 'mutualise' => 1],
                ['id' => 3, 'diplome_id' => 1, 'semestre_id' => 1, 'libelle' => 'TP', 'type' => 'TP', 'defaut' => 0, 'mutualise' => 1],
                ['id' => 4, 'diplome_id' => 1, 'semestre_id' => 3, 'libelle' => 'CM', 'type' => 'CM', 'defaut' => 1, 'mutualise' => 0],
                ['id' => 5, 'diplome_id' => 2, 'semestre_id' => 4, 'libelle' => 'CM', 'type' => 'CM', 'defaut' => 1, 'mutualise' => 0],
            ],
            // Les groupes du premier semestre servent aussi au second : c'est le cas courant en V3.
            'type_groupe_semestre' => [
                ['type_groupe_id' => 1, 'semestre_id' => 1],
                ['type_groupe_id' => 1, 'semestre_id' => 2],
                ['type_groupe_id' => 2, 'semestre_id' => 1],
                ['type_groupe_id' => 2, 'semestre_id' => 2],
                ['type_groupe_id' => 3, 'semestre_id' => 1],
                ['type_groupe_id' => 3, 'semestre_id' => 2],
                ['type_groupe_id' => 4, 'semestre_id' => 3],
                ['type_groupe_id' => 5, 'semestre_id' => 4],
            ],
            'groupe' => [
                ['id' => 1, 'type_groupe_id' => 1, 'libelle' => 'Promo MMI 1', 'code_apogee' => 'MMI1CM', 'ordre' => 1],
                ['id' => 2, 'type_groupe_id' => 2, 'parent_id' => 1, 'libelle' => 'TD AB', 'code_apogee' => 'MMI1TDAB', 'ordre' => 1],
                ['id' => 3, 'type_groupe_id' => 3, 'parent_id' => 2, 'libelle' => 'TP A', 'code_apogee' => 'MMI1TPA', 'ordre' => 1],
                ['id' => 4, 'type_groupe_id' => 3, 'parent_id' => 2, 'libelle' => 'TP B', 'code_apogee' => 'MMI1TPB', 'ordre' => 2],
                ['id' => 5, 'type_groupe_id' => 4, 'libelle' => 'Promo MMI 2', 'code_apogee' => 'MMI2CM', 'ordre' => 1, 'apc_parcours_id' => 1],
                ['id' => 6, 'type_groupe_id' => 5, 'libelle' => 'LP DW', 'code_apogee' => 'LPCM', 'ordre' => 1],
            ],
            'salle' => [
                ['id' => 1, 'libelle' => 'AMPHI', 'capacite' => 120, 'type' => 'amphi'],
                ['id' => 2, 'libelle' => 'B204', 'capacite' => 30, 'type' => 'td'],
                ['id' => 3, 'libelle' => 'C110', 'capacite' => 20, 'type' => 'info'],
            ],
            'calendrier' => [
                ['id' => 1, 'annee_universitaire_id' => 1, 'semaine_formation' => 1, 'semaine_reelle' => 37, 'date_lundi' => '2025-09-08 00:00:00'],
                ['id' => 2, 'annee_universitaire_id' => 2, 'semaine_formation' => 1, 'semaine_reelle' => 37, 'date_lundi' => '2026-09-07 00:00:00'],
                ['id' => 3, 'annee_universitaire_id' => 2, 'semaine_formation' => 2, 'semaine_reelle' => 38, 'date_lundi' => '2026-09-14 00:00:00'],
                ['id' => 4, 'annee_universitaire_id' => 2, 'semaine_formation' => 5, 'semaine_reelle' => 41, 'date_lundi' => '2026-10-05 00:00:00'],
            ],
            'bac' => [
                ['id' => 1, 'libelle' => 'Général', 'libelle_long' => 'Baccalauréat général', 'code_apogee' => '0031', 'type_bac' => 'g'],
                ['id' => 2, 'libelle' => 'STI2D', 'libelle_long' => 'Sciences et technologies de l\'industrie et du développement durable', 'code_apogee' => '0034', 'type_bac' => 't'],
            ],
            'adresse' => [
                ['id' => 1, 'adresse1' => '12 rue de la Cité', 'code_postal' => '10000', 'ville' => 'Troyes', 'pays' => 'France'],
                ['id' => 2, 'adresse1' => '4 place du Marché', 'adresse2' => 'Bâtiment B', 'code_postal' => '51100', 'ville' => 'Reims', 'pays' => 'France'],
                ['id' => 3, 'adresse1' => '8 avenue Pasteur', 'code_postal' => '10000', 'ville' => 'Troyes', 'pays' => 'France'],
                ['id' => 4, 'adresse1' => 'Technopole de l\'Aube', 'code_postal' => '10430', 'ville' => 'Rosières-près-Troyes', 'pays' => 'France'],
            ],
            'etudiant' => [
                ['adresse_id' => 1, 'adresse_parentale_id' => 2, 'boursier' => 1, 'date_naissance' => '2008-03-14 00:00:00', 'lieu_naissance' => 'Troyes', 'tel1' => '0601020304'] + $this->student(1, 'durand', 'Léa', 'Durand', '22600001', 1, 2029),
                ['bac_id' => 2, 'amenagements_particuliers' => 'Tiers temps'] + $this->student(2, 'martin', 'Hugo', 'Martin', '22600002', 1, 2029),
                ['formation_continue' => 1] + $this->student(3, 'petit', 'Inès', 'Petit', '22600003', 4, 2027),
                // Diplômé ou parti : ses notes ne sont pas migrées, son passé l'est.
                ['annee_sortie' => 2025] + $this->student(4, 'leroy', 'Paul', 'Leroy', '22500004', null, 2028),
                $this->student(5, 'moreau', 'Sarah', 'Moreau', '22500005', 3, 2028),
            ],
            'etudiant_groupe' => [
                ['etudiant_id' => 1, 'groupe_id' => 1], ['etudiant_id' => 1, 'groupe_id' => 2], ['etudiant_id' => 1, 'groupe_id' => 3],
                ['etudiant_id' => 2, 'groupe_id' => 1], ['etudiant_id' => 2, 'groupe_id' => 2], ['etudiant_id' => 2, 'groupe_id' => 4],
                ['etudiant_id' => 3, 'groupe_id' => 6],
                ['etudiant_id' => 5, 'groupe_id' => 5],
            ],
            'scolarite' => [
                $this->enrolment(1, 1, 1, 2, 1, 'E.C.'),
                $this->enrolment(2, 2, 1, 2, 1, 'E.C.'),
                $this->enrolment(3, 3, 4, 2, 1, 'E.C.'),
                $this->enrolment(4, 5, 3, 2, 3, 'E.C.'),
                ['moyenne' => 13.2, 'nb_absences' => 2, 'diffuse' => 1, 'rang' => 5, 'proposition' => 'S2'] + $this->enrolment(5, 5, 1, 1, 1, 'V'),
                ['moyenne' => 12.4, 'diffuse' => 1, 'rang' => 8, 'proposition' => 'S3'] + $this->enrolment(6, 5, 2, 1, 2, 'V'),
                ['moyenne' => 6.5, 'nb_absences' => 14, 'diffuse' => 1, 'commentaire' => 'Démission en novembre'] + $this->enrolment(7, 4, 1, 1, 1, 'DEM'),
            ],
            'evaluation' => [
                ['type_groupe_id' => 2] + $this->assessment(1, 2, 1, 'ressource', 1, 'Compréhension écrite', '2026-10-05'),
                ['coefficient' => 2] + $this->assessment(2, 2, 1, 'ressource', 2, 'Projet web', '2026-10-12'),
                ['parent_id' => 2] + $this->assessment(3, 2, 1, 'ressource', 2, 'Projet web : maquette', '2026-10-12'),
                $this->assessment(4, 2, 1, 'sae', 1, 'Soutenance', '2026-10-16'),
                $this->assessment(5, 1, 1, 'ressource', 1, 'Oral d\'anglais', '2025-11-20'),
                $this->assessment(6, 2, 4, 'matiere', 1, 'API REST', '2026-10-09'),
            ],
            'note' => [
                $this->grade(1, 1, 1, 14.5), $this->grade(2, 1, 2, 9.0),
                $this->grade(3, 3, 1, 16.0), ['absence_justifie' => 1, 'commentaire' => 'Absence justifiée'] + $this->grade(4, 3, 2, 0.0),
                $this->grade(5, 4, 1, 12.0), $this->grade(6, 4, 2, 11.5),
                $this->grade(7, 5, 5, 13.0), $this->grade(8, 5, 4, 7.5),
                $this->grade(9, 6, 3, 15.0),
            ],
            'edt_planning' => [
                $this->timetableEvent(1, 2, '2026-10-05', 1, '08:00', '10:00', 'CM', 1, 'AMPHI', 1, 'ressource', 1),
                $this->timetableEvent(2, 2, '2026-10-05', 1, '10:00', '12:00', 'TD', 2, 'B204', 2, 'ressource', 1),
                ['evaluation' => 1] + $this->timetableEvent(3, 2, '2026-10-06', 2, '13:30', '15:30', 'TP', 3, 'C110', 3, 'ressource', 3),
                $this->timetableEvent(4, 2, '2026-10-07', 3, '09:00', '12:00', 'TD', 2, 'B204', 1, 'sae', 1),
                // Un créneau libre, sans enseignement : il doit être gardé.
                ['texte' => 'Réunion de rentrée', 'semaine' => 1] + $this->timetableEvent(5, 2, '2026-09-07', 1, '09:00', '10:00', 'CM', 1, 'AMPHI', 1, null, 0),
                $this->timetableEvent(6, 1, '2025-11-20', 4, '08:00', '10:00', 'TD', 2, 'B204', 2, 'ressource', 1),
            ],
            // La première absence correspond au créneau no 2, par sa date et son heure.
            'absence' => [
                $this->absence(1, 1, 2, '2026-10-05 10:00:00', 'ressource', 1),
                ['justifie' => 1, 'date_justifie' => '2026-10-08 09:00:00'] + $this->absence(2, 2, 3, '2026-10-06 13:30:00', 'ressource', 3),
                ['annee_universitaire_id' => 1] + $this->absence(3, 5, 2, '2025-11-20 08:00:00', 'ressource', 1),
            ],
            'absence_justificatif' => [
                $this->justification(1, 1, '2026-10-05 08:00:00', '2026-10-05 18:00:00', 'Rendez-vous médical', 'D'),
                ['fichier_name' => 'convocation.pdf'] + $this->justification(2, 2, '2026-10-06 08:00:00', '2026-10-06 18:00:00', 'Convocation au permis de conduire', 'A'),
                $this->justification(3, 1, '2026-09-21 08:00:00', '2026-09-21 12:00:00', 'Panne de réveil', 'R'),
                ['annee_universitaire_id' => 1] + $this->justification(4, 5, '2025-11-20 08:00:00', '2025-11-20 18:00:00', 'Maladie', 'A'),
            ],
            'rattrapage' => [
                [
                    'id' => 1, 'etudiant_id' => 1, 'personnel_id' => 2, 'semestre_id' => 1, 'annee_universitaire_id' => 2,
                    'type_matiere' => 'ressource', 'id_matiere' => 1, 'date_eval' => '2026-10-05 00:00:00', 'heure_eval' => '1970-01-01 10:00:00',
                    'duree' => '1h', 'date_rattrapage' => '2026-10-19 00:00:00', 'heure_rattrapage' => '1970-01-01 14:00:00', 'salle' => 'B204', 'etat_demande' => 'a',
                ],
                ['id' => 2, 'etudiant_id' => 2, 'semestre_id' => 1, 'annee_universitaire_id' => 2, 'type_matiere' => 'sae', 'id_matiere' => 1, 'date_eval' => '2026-10-16 00:00:00', 'etat_demande' => 'f'],
                ['id' => 3, 'etudiant_id' => 5, 'semestre_id' => 1, 'annee_universitaire_id' => 1, 'type_matiere' => 'ressource', 'id_matiere' => 1, 'date_eval' => '2025-11-20 00:00:00', 'etat_demande' => 'r'],
            ],
            'type_hrs' => [
                ['id' => 1, 'libelle' => 'Suivi des stages', 'type' => 'Suivi', 'inclu_service' => 1, 'maximum' => 10],
                ['id' => 2, 'libelle' => 'Responsabilité de diplôme', 'type' => 'PRP', 'inclu_service' => 0, 'maximum' => 48],
            ],
            'hrs' => [
                ['id' => 1, 'personnel_id' => 1, 'type_hrs_id' => 2, 'diplome_id' => 1, 'departement_id' => 1, 'nb_heures_td' => 48, 'libelle' => 'Direction des études', 'annee' => 2026],
                ['id' => 2, 'personnel_id' => 2, 'type_hrs_id' => 1, 'semestre_id' => 4, 'diplome_id' => 2, 'departement_id' => 1, 'nb_heures_td' => 6, 'libelle' => 'Suivi des stages LP', 'commentaire' => 'Trois étudiants', 'annee' => 2026],
                ['id' => 3, 'personnel_id' => 1, 'type_hrs_id' => 2, 'diplome_id' => 1, 'departement_id' => 1, 'nb_heures_td' => 48, 'libelle' => 'Direction des études', 'annee' => 2025],
            ],
            'previsionnel' => [
                ['referent' => 1] + $this->forecast(1, 2, 'ressource', 1, 10, 20, 0),
                $this->forecast(2, 3, 'ressource', 3, 0, 0, 30),
                $this->forecast(3, 1, 'sae', 1, 0, 10, 10),
                $this->forecast(4, 2, 'matiere', 1, 10, 10, 20),
                ['annee' => 2025] + $this->forecast(5, 2, 'ressource', 1, 10, 20, 0),
            ],
            'type_document' => [
                ['id' => 1, 'departement_id' => 1, 'libelle' => 'Scolarité', 'originaux' => 0],
                ['id' => 2, 'departement_id' => 1, 'parent_id' => 1, 'libelle' => 'Règlements', 'originaux' => 0],
                ['id' => 3, 'departement_id' => 1, 'libelle' => 'Originaux', 'originaux' => 1],
            ],
            'document' => [
                ['id' => 1, 'type_document_id' => 2, 'libelle' => 'Règlement des études', 'description' => 'Version votée en conseil', 'document_name' => 'reglement-etudes.pdf', 'type_fichier' => 'application/pdf', 'taille' => 61, 'type_destinataire' => 'ALL', 'created' => '2026-09-01 10:00:00', 'updated' => '2026-09-02 10:00:00'],
                ['id' => 2, 'type_document_id' => 1, 'libelle' => 'Charte informatique', 'document_name' => 'charte.pdf', 'type_fichier' => 'application/pdf', 'taille' => 60, 'type_destinataire' => 'ETU', 'created' => '2026-09-01 10:00:00', 'updated' => '2026-09-01 10:00:00'],
                ['id' => 3, 'type_document_id' => 3, 'libelle' => 'Procès-verbal de jury', 'document_name' => 'pv-jury.xlsx', 'type_fichier' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'taille' => 62, 'type_destinataire' => 'PERM', 'created' => '2026-07-01 10:00:00', 'updated' => '2026-07-01 10:00:00'],
            ],
            'contact' => [
                ['id' => 1, 'nom' => 'Bernard', 'prenom' => 'Claire', 'fonction' => 'Développeuse senior', 'telephone' => '0325000001', 'email' => 'claire.bernard@pixel.example', 'civilite' => 'MME'],
                ['id' => 2, 'nom' => 'Roux', 'prenom' => 'Antoine', 'fonction' => 'Gérant', 'telephone' => '0325000002', 'email' => 'antoine.roux@pixel.example', 'portable' => '0612345678', 'civilite' => 'M.'],
            ],
            'entreprise' => [
                ['id' => 1, 'adresse_id' => 3, 'responsable_id' => 2, 'siret' => '12345678900012', 'raison_sociale' => 'Agence Pixel'],
            ],
            'stage_periode' => [
                [
                    'id' => 1, 'semestre_id' => 4, 'annee_universitaire_id' => 2, 'numero_periode' => 1, 'libelle' => 'Stage de licence professionnelle',
                    'nb_semaines' => 12, 'nb_jours' => 60, 'date_debut' => '2027-04-06 00:00:00', 'date_fin' => '2027-06-25 00:00:00',
                    'competences_visees' => 'Développer une application web', 'modalite_evaluation' => 'Rapport et soutenance', 'modalite_evaluation_pedagogique' => 'Grille commune',
                    'modalite_encadrement' => 'Visite du tuteur universitaire', 'document_rendre' => 'Rapport de stage', 'nb_ects' => 12, 'dates_flexibles' => 1, 'texte_libre' => 'Stage à temps plein',
                ],
                ['id' => 2, 'semestre_id' => 4, 'annee_universitaire_id' => 1, 'numero_periode' => 1, 'libelle' => 'Stage 2025-2026', 'nb_semaines' => 12, 'nb_jours' => 60, 'date_debut' => '2026-04-07 00:00:00', 'date_fin' => '2026-06-26 00:00:00'],
            ],
            'stage_periode_personnel' => [
                ['stage_periode_id' => 1, 'personnel_id' => 1],
                ['stage_periode_id' => 1, 'personnel_id' => 2],
            ],
            'stage_etudiant' => [
                [
                    'id' => 1, 'stage_periode_id' => 1, 'etudiant_id' => 3, 'entreprise_id' => 1, 'tuteur_id' => 1, 'tuteur_universitaire_id' => 2, 'adresse_stage_id' => 4,
                    'service_stage_entreprise' => 'Pôle web', 'sujet_stage' => 'Refonte d\'une application de réservation', 'activites' => 'Développement Symfony et Vue',
                    'date_depot_formulaire' => '2027-01-15 00:00:00', 'date_validation' => '2027-01-20 00:00:00', 'date_convention_envoyee' => '2027-01-25 00:00:00', 'date_convention_recu' => '2027-02-10 00:00:00',
                    'etat_stage' => 'ETAT_STAGE_CONVENTION_RECUE', 'date_debut_stage' => '2027-04-06 00:00:00', 'date_fin_stage' => '2027-06-25 00:00:00',
                    'gratification' => 1, 'gratification_montant' => 4.35, 'gratification_periode' => 'H', 'avantages' => 'Titres-restaurant',
                    'duree_hebdomadaire' => 35, 'duree_jours_stage' => 60, 'periodes_interruptions' => 'Aucune',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function staff(int $id, string $username, string $firstName, string $lastName, string $status, string $initials): array
    {
        return [
            'id' => $id, 'username' => $username, 'prenom' => $firstName, 'nom' => $lastName, 'slug' => $username, 'type_user' => 'personnel',
            'mail_univ' => $username . '@univ-reims.fr', 'mail_perso' => $username . '@example.fr', 'roles' => '["ROLE_PERMANENT"]',
            'statut' => $status, 'initiales' => $initials, 'photo_name' => 'noimage.png', 'nb_heures_service' => 'vacataire' === $status ? 0 : 192,
            'annee_universitaire_id' => 2, 'poste_interne' => '7100', 'tel_bureau' => '0325427100', 'bureau1' => 'B101',
            'responsabilites' => 'Responsable de modules', 'domaines' => '["Web"]', 'numero_harpege' => (string) (90000 + $id), 'visible' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function diploma(int $id, string $label, string $acronym, int $type): array
    {
        return [
            'id' => $id, 'departement_id' => 1, 'type_diplome_id' => $type, 'libelle' => $label, 'sigle' => $acronym, 'actif' => 1,
            'volume_horaire' => 1800, 'code_celcat_departement' => 123, 'opt_nb_jours_saisie' => 15, 'opt_semaines_visibles' => 2,
            'opt_methode_calcul' => 'moyenne', 'opt_espace_perso_visible' => 1, 'key_edu_sign' => 'cle-' . $id, 'saisie_cm_autorise' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function semester(int $id, int $year, string $label, int $yearOrder, int $lmdOrder, string $code): array
    {
        return [
            'id' => $id, 'annee_id' => $year, 'libelle' => $label, 'ordre_annee' => $yearOrder, 'ordre_lmd' => $lmdOrder, 'actif' => 1,
            'nb_groupes_cm' => 1, 'nb_groupes_td' => 1, 'nb_groupes_tp' => 2, 'code_element' => $code, 'mois_debut' => 1 === $yearOrder ? 9 : 1,
            'opt_evaluation_visible' => 1, 'opt_evaluation_modifiable' => 1, 'opt_penalite_absence' => 1, 'opt_point_penalite_absence' => 0.5, 'opt_rattrapage' => 1,
            'opt_dest_mail_releve_id' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function subject(int $id, int $unit, string $label, string $shortLabel, string $code, string $element, int $coefficient): array
    {
        return [
            'id' => $id, 'ue_id' => $unit, 'libelle' => $label, 'libelle_court' => $shortLabel, 'description' => 'Matière de licence',
            'cm_ppn' => 10, 'td_ppn' => 10, 'tp_ppn' => 20, 'cm_formation' => 10, 'td_formation' => 10, 'tp_formation' => 20,
            'code_matiere' => $code, 'code_element' => $element, 'nb_notes' => 2, 'suspendu' => 0, 'mutualisee' => 0, 'pac' => 0,
            'coefficient' => $coefficient, 'nb_ects' => 3 * $coefficient, 'objectifs_module' => 'Objectifs', 'competences_visees' => 'Compétences',
            'contenu' => 'Contenu', 'pre_requis' => 'Aucun', 'modalites' => 'Contrôle continu', 'prolongements' => 'Stage', 'mots_cles' => 'web',
        ];
    }

    /** @return array<string, mixed> */
    private function resource(int $id, string $label, string $code, string $element): array
    {
        return [
            'id' => $id, 'libelle' => $label, 'libelle_court' => $code, 'description' => 'Ressource du premier semestre',
            'cm_ppn' => 6, 'td_ppn' => 12, 'tp_ppn' => 12, 'cm_formation' => 6, 'td_formation' => 12, 'tp_formation' => 12,
            'code_matiere' => $code, 'code_element' => $element, 'nb_notes' => 2, 'suspendu' => 0, 'mutualisee' => 0,
            'pre_requis' => 'Aucun', 'mots_cles' => 'mmi', 'has_coefficient_different' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function student(int $id, string $username, string $firstName, string $lastName, string $number, ?int $semester, int $promotion): array
    {
        return [
            'id' => $id, 'username' => $username, 'prenom' => $firstName, 'nom' => $lastName, 'slug' => $username, 'type_user' => 'etudiant',
            'mail_univ' => $username . '@etudiant.univ-reims.fr', 'mail_perso' => $username . '@example.fr', 'roles' => '["ROLE_ETUDIANT"]',
            'num_etudiant' => $number, 'num_ine' => 'INE' . $number, 'semestre_id' => $semester, 'departement_id' => 1, 'bac_id' => 1,
            'annee_bac' => $promotion - 3, 'promotion' => $promotion, 'annee_sortie' => 0, 'photo_name' => 'noimage.png', 'visible' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function enrolment(int $id, int $student, int $semester, int $academicYear, int $order, string $decision): array
    {
        return [
            'id' => $id, 'etudiant_id' => $student, 'semestre_id' => $semester, 'annee_universitaire_id' => $academicYear,
            'ordre' => $order, 'decision' => $decision, 'moyenne' => 0, 'nb_absences' => 0, 'diffuse' => 0, 'rang' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function assessment(int $id, int $academicYear, int $semester, string $subjectType, int $subject, string $label, string $date): array
    {
        return [
            'id' => $id, 'annee_universitaire_id' => $academicYear, 'semestre_id' => $semester, 'personnel_auteur_id' => 2,
            'type_matiere' => $subjectType, 'id_matiere' => $subject, 'libelle' => $label, 'date_evaluation' => $date . ' 00:00:00',
            'visible' => 1, 'modifiable' => 1, 'coefficient' => 1, 'commentaire' => 'Évaluation de ' . $label,
        ];
    }

    /** @return array<string, mixed> */
    private function grade(int $id, int $assessment, int $student, float $value): array
    {
        return ['id' => $id, 'evaluation_id' => $assessment, 'etudiant_id' => $student, 'note' => $value, 'absence_justifie' => 0];
    }

    /** @return array<string, mixed> */
    private function timetableEvent(int $id, int $academicYear, string $date, int $day, string $start, string $end, string $type, int $group, string $room, int $staff, ?string $subjectType, int $subject): array
    {
        return [
            'id' => $id, 'annee_universitaire_id' => $academicYear, 'semestre_id' => 1, 'diplome_id' => 1, 'intervenant_id' => $staff,
            'date' => $date . ' 00:00:00', 'jour' => $day, 'semaine' => 5, 'ordre_semestre' => 1, 'ordre' => '1',
            'heure_debut' => $date . ' ' . $start . ':00', 'heure_fin' => $date . ' ' . $end . ':00', 'debut' => 1, 'fin' => 5,
            'type' => $type, 'groupe' => $group, 'groupe_objet_id' => $group, 'salle' => $room, 'evaluation' => 0,
            'type_matiere' => $subjectType, 'id_matiere' => $subject, 'id_edu_sign' => 'edusign-' . $id,
        ];
    }

    /** @return array<string, mixed> */
    private function absence(int $id, int $student, int $staff, string $dateTime, string $subjectType, int $subject): array
    {
        return [
            'id' => $id, 'etudiant_id' => $student, 'personnel_id' => $staff, 'annee_universitaire_id' => 2, 'semestre_id' => 1,
            'dateHeure' => $dateTime, 'duree' => '1970-01-01 02:00:00', 'justifie' => 0, 'type_matiere' => $subjectType, 'id_matiere' => $subject,
        ];
    }

    /** @return array<string, mixed> */
    private function justification(int $id, int $student, string $start, string $end, string $reason, string $state): array
    {
        return [
            'id' => $id, 'etudiant_id' => $student, 'annee_universitaire_id' => 2, 'semestre_id' => 1,
            'date_heure_debut' => $start, 'date_heure_fin' => $end, 'motif' => $reason, 'etat' => $state,
        ];
    }

    /** @return array<string, mixed> */
    private function forecast(int $id, int $staff, string $subjectType, int $subject, float $lectureHours, float $tutorialHours, float $practicalHours): array
    {
        return [
            'id' => $id, 'personnel_id' => $staff, 'annee' => 2026, 'referent' => 0, 'type_matiere' => $subjectType, 'id_matiere' => $subject,
            'nb_hcm' => $lectureHours, 'nb_htd' => $tutorialHours, 'nb_htp' => $practicalHours, 'nb_gr_cm' => 1, 'nb_gr_td' => 1, 'nb_gr_tp' => 2,
        ];
    }
}
