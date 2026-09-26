<?php

declare(strict_types=1);

namespace App\Command\Celcat;

use App\DataFixtures\StructureEnseignantFixtures;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Crée une base SQLite qui imite Celcat, pour développer sans accès à la vraie.
 *
 * Seules les tables et colonnes lues par CelcatReader existent. Les codes de groupes, de
 * département et d'enseignants sont ceux des fixtures, pour que les créneaux s'y rattachent.
 */
#[When(env: 'dev')]
#[AsCommand(
    name: 'app:celcat:fausse-base',
    description: 'Crée une fausse base Celcat SQLite pour le développement',
)]
final class CelcatFakeDatabaseCommand extends Command
{
    private const DEPARTMENT = 123;

    /** Semaines sans cours, comptées depuis la rentrée : Toussaint et Noël. */
    private const HOLIDAYS = [8, 16, 17];

    /** Le premier semestre court sur les vingt premières semaines. */
    private const SEMESTER_WEEKS = 20;

    private const GROUPS = [
        'MMICM' => 'CM',
        'MMITDAB' => 'TD AB',
        'MMITDCD' => 'TD CD',
        'MMITPA' => 'TP A',
        'MMITPB' => 'TP B',
        'MMITPC' => 'TP C',
        'MMITPD' => 'TP D',
    ];

    private const SUBJECTS = [
        'R1.01' => 'Anglais',
        'R1.02' => 'Culture numérique',
        'R1.03' => 'Stratégies de communication',
        'R1.05' => 'Écriture multimédia et narration',
        'R1.06' => 'Production graphique',
        'R1.10' => 'Intégration',
        'R1.11' => 'Développement web',
        'SAE1.01' => 'Recommandation de communication numérique',
    ];

    private const ROOMS = [
        'AMPHI' => 'Amphithéâtre',
        'B204' => 'Salle B204',
        'B205' => 'Salle B205',
        'C110' => 'Labo C110',
        'C112' => 'Labo C112',
    ];

    /**
     * Une semaine type : jour (0 = lundi), début, fin, catégorie, matière, enseignant, salle,
     * groupes. Un CM commun à toute la promotion n'est qu'un événement Celcat, rattaché au
     * seul groupe de CM ; les TD et TP sont des événements distincts par groupe.
     *
     * @var list<array{int, string, string, string, string, string, string, list<string>}>
     */
    private const WEEK_TEMPLATE = [
        [0, '08:00', '10:00', '[CM]', 'R1.02', '10001', 'AMPHI', ['MMICM']],
        [0, '10:15', '12:15', '[TD]', 'R1.06', '10003', 'B204', ['MMITDAB']],
        [0, '10:15', '12:15', '[TD]', 'R1.03', '10002', 'B205', ['MMITDCD']],
        [0, '13:30', '15:30', '[TP]', 'R1.10', '10004', 'C110', ['MMITPA']],
        [0, '13:30', '15:30', '[TP]', 'R1.10', '10005', 'C112', ['MMITPB']],
        [1, '08:00', '10:00', '[CM]', 'R1.11', '10004', 'AMPHI', ['MMICM']],
        [1, '10:15', '12:15', '[TP]', 'R1.11', '10004', 'C110', ['MMITPC']],
        [1, '10:15', '12:15', '[TP]', 'R1.11', '10005', 'C112', ['MMITPD']],
        [1, '13:30', '15:30', '[TD]', 'R1.01', '10002', 'B204', ['MMITDAB']],
        [2, '08:00', '10:00', '[CM]', 'R1.05', '10001', 'AMPHI', ['MMICM']],
        [2, '10:15', '12:15', '[TP]', 'R1.11', '10004', 'C110', ['MMITPA']],
        [2, '10:15', '12:15', '[TP]', 'R1.11', '10005', 'C112', ['MMITPB']],
        [3, '08:00', '12:15', '[TD]', 'SAE1.01', '10003', 'B204', ['MMITDAB']],
        [3, '08:00', '12:15', '[TD]', 'SAE1.01', '10002', 'B205', ['MMITDCD']],
        [3, '13:30', '15:30', '[TD]', 'R1.01', '10002', 'B205', ['MMITDCD']],
        [4, '09:00', '11:00', '[TP]', 'R1.06', '10003', 'C110', ['MMITPA']],
        [4, '09:00', '11:00', '[TP]', 'R1.06', '10005', 'C112', ['MMITPC']],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/celcat/fausse-base.sqlite')]
        private readonly string $file,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->filesystem->remove($this->file);
        $this->filesystem->mkdir(\dirname($this->file));
        $database = new \PDO('sqlite:'.$this->file, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $this->createTables($database);
        $startDate = $this->startOfWeek(new \DateTimeImmutable('today'));
        $this->fillWeeks($database, $startDate);
        $count = $this->fillEvents($database);

        $io->success(sprintf('Fausse base Celcat créée : %d événements, rentrée le %s.', $count, $startDate->format('d/m/Y')));
        $io->text([
            'Pour la brancher, ajouter dans back/.env.local :',
            '  CELCAT_DSN="sqlite:%kernel.project_dir%/var/celcat/fausse-base.sqlite"',
            'puis lancer : php bin/console app:celcat:sync',
        ]);

        return Command::SUCCESS;
    }

    private function createTables(\PDO $database): void
    {
        $database->exec('CREATE TABLE CT_DEPT (dept_id INTEGER PRIMARY KEY, name TEXT)');
        $database->exec('CREATE TABLE CT_WEEK_CONFIG (week_no INTEGER PRIMARY KEY, week_date TEXT)');
        $database->exec('CREATE TABLE CT_EVENT_CAT (event_cat_id INTEGER PRIMARY KEY, name TEXT)');
        $database->exec('CREATE TABLE CT_EVENT (event_id INTEGER PRIMARY KEY, dept_id INTEGER, event_cat_id INTEGER,
            day_of_week INTEGER, start_time TEXT, end_time TEXT, weeks TEXT, date_change TEXT, notes TEXT)');
        foreach (['MODULE', 'STAFF', 'GROUP'] as $view) {
            $database->exec(sprintf('CREATE TABLE CT_VIEW_EVENT_%s001 (eid INTEGER, resourcecode TEXT, resourcename TEXT)', $view));
        }
        $database->exec('CREATE TABLE CT_VIEW_EVENT_ROOM001 (eid INTEGER, resourcecode TEXT, resourcename TEXT, resourceweeks TEXT)');

        $database->exec("INSERT INTO CT_DEPT VALUES (".self::DEPARTMENT.", 'MMI Troyes')");
    }

    /**
     * La rentrée tombe la semaine du 1er septembre : avant la mi-août, on est encore dans
     * l'année commencée l'automne précédent.
     */
    private function startOfWeek(\DateTimeImmutable $today): \DateTimeImmutable
    {
        $academicYear = (int) $today->format('n') >= 8 && (int) $today->format('j') >= 15 || (int) $today->format('n') > 8
            ? (int) $today->format('Y')
            : (int) $today->format('Y') - 1;

        return (new \DateTimeImmutable($academicYear.'-09-01'))->modify('monday this week');
    }

    private function fillWeeks(\PDO $database, \DateTimeImmutable $startDate): void
    {
        $insertion = $database->prepare('INSERT INTO CT_WEEK_CONFIG VALUES (?, ?)');
        for ($week = 0; $week < 43; ++$week) {
            $insertion->execute([$week, $startDate->modify('+'.$week.' weeks')->format('Y-m-d 00:00:00')]);
        }
    }

    private function fillEvents(\PDO $database): int
    {
        $categoryIds = [];
        $categoryInsert = $database->prepare('INSERT INTO CT_EVENT_CAT VALUES (?, ?)');
        foreach (['[CM]', '[TD]', '[TP]', 'Réunion'] as $index => $name) {
            $categoryInsert->execute([$index + 1, $name]);
            $categoryIds[$name] = $index + 1;
        }

        $mask = $this->semesterMask();
        $event = 1000;
        $changed = (new \DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s');

        foreach (self::WEEK_TEMPLATE as [$day, $start, $end, $category, $subject, $teacher, $room, $groups]) {
            ++$event;
            $this->insertEvent($database, $event, $categoryIds[$category], $day, $start, $end, $mask, $changed, null);
            $this->resource($database, 'MODULE', $event, $subject, self::SUBJECTS[$subject]);
            ['nom' => $name, 'prenom' => $firstName] = StructureEnseignantFixtures::ENSEIGNANTS[$teacher];
            $this->resource($database, 'STAFF', $event, $teacher, $name.' '.$firstName);
            $database->prepare('INSERT INTO CT_VIEW_EVENT_ROOM001 VALUES (?, ?, ?, NULL)')
                ->execute([$event, $room, self::ROOMS[$room]]);
            foreach ($groups as $group) {
                $this->resource($database, 'GROUP', $event, $group, self::GROUPS[$group]);
            }
        }

        // Un événement sans matière, pour exercer le cas « hors cours » de la conversion.
        ++$event;
        $this->insertEvent($database, $event, $categoryIds['Réunion'], 0, '16:00', '17:00', 'Y', $changed, 'de rentrée');
        $this->resource($database, 'GROUP', $event, 'MMICM', self::GROUPS['MMICM']);
        $database->prepare('INSERT INTO CT_VIEW_EVENT_ROOM001 VALUES (?, ?, ?, NULL)')
            ->execute([$event, 'AMPHI', self::ROOMS['AMPHI']]);

        return $event - 1000;
    }

    private function semesterMask(): string
    {
        $mask = '';
        for ($week = 0; $week < self::SEMESTER_WEEKS; ++$week) {
            $mask .= \in_array($week, self::HOLIDAYS, true) ? 'N' : 'Y';
        }

        return $mask;
    }

    private function insertEvent(\PDO $database, int $id, int $category, int $day, string $start, string $end, string $mask, string $changed, ?string $notes): void
    {
        // Celcat range les heures dans une date fictive, 30 décembre 1899.
        $database->prepare('INSERT INTO CT_EVENT VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $id, self::DEPARTMENT, $category, $day,
            '1899-12-30 '.$start.':00', '1899-12-30 '.$end.':00',
            $mask, $changed, $notes,
        ]);
    }

    private function resource(\PDO $database, string $view, int $event, string $code, string $name): void
    {
        $database->prepare(sprintf('INSERT INTO CT_VIEW_EVENT_%s001 VALUES (?, ?, ?)', $view))->execute([$event, $code, $name]);
    }
}
