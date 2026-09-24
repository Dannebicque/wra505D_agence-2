<?php

namespace App\Command\Celcat;

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
 * Seules les tables et colonnes lues par CelcatReader existent. Les codes de groupes et de
 * département sont ceux des fixtures, pour que les créneaux s'y rattachent.
 */
#[When(env: 'dev')]
#[AsCommand(
    name: 'app:celcat:fausse-base',
    description: 'Crée une fausse base Celcat SQLite pour le développement',
)]
final class CelcatFausseBaseCommand extends Command
{
    private const DEPARTEMENT = 123;

    /** Semaines sans cours, comptées depuis la rentrée : Toussaint et Noël. */
    private const VACANCES = [8, 16, 17];

    /** Le premier semestre court sur les vingt premières semaines. */
    private const SEMAINES_DU_SEMESTRE = 20;

    private const GROUPES = [
        'MMICM' => 'CM',
        'MMITDAB' => 'TD AB',
        'MMITDCD' => 'TD CD',
        'MMITPA' => 'TP A',
        'MMITPB' => 'TP B',
        'MMITPC' => 'TP C',
        'MMITPD' => 'TP D',
    ];

    private const MATIERES = [
        'R1.01' => 'Anglais',
        'R1.02' => 'Culture numérique',
        'R1.03' => 'Stratégies de communication',
        'R1.05' => 'Écriture multimédia et narration',
        'R1.06' => 'Production graphique',
        'R1.10' => 'Intégration',
        'R1.11' => 'Développement web',
        'SAE1.01' => 'Recommandation de communication numérique',
    ];

    private const ENSEIGNANTS = [
        '10001' => 'Martin Claire',
        '10002' => 'Lefèvre Hélène',
        '10003' => 'Garnier Thomas',
        '10004' => 'Roussel Inès',
        '10005' => 'Chevalier Marc',
    ];

    private const SALLES = [
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
    private const SEMAINE_TYPE = [
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
        private readonly string $fichier,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->filesystem->remove($this->fichier);
        $this->filesystem->mkdir(\dirname($this->fichier));
        $base = new \PDO('sqlite:'.$this->fichier, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $this->creerTables($base);
        $rentree = $this->lundiDeLaRentree(new \DateTimeImmutable('today'));
        $this->remplirSemaines($base, $rentree);
        $nombre = $this->remplirEvenements($base);

        $io->success(sprintf('Fausse base Celcat créée : %d événements, rentrée le %s.', $nombre, $rentree->format('d/m/Y')));
        $io->text([
            'Pour la brancher, ajouter dans back/.env.local :',
            '  CELCAT_DSN="sqlite:%kernel.project_dir%/var/celcat/fausse-base.sqlite"',
            'puis lancer : php bin/console app:celcat:sync',
        ]);

        return Command::SUCCESS;
    }

    private function creerTables(\PDO $base): void
    {
        $base->exec('CREATE TABLE CT_DEPT (dept_id INTEGER PRIMARY KEY, name TEXT)');
        $base->exec('CREATE TABLE CT_WEEK_CONFIG (week_no INTEGER PRIMARY KEY, week_date TEXT)');
        $base->exec('CREATE TABLE CT_EVENT_CAT (event_cat_id INTEGER PRIMARY KEY, name TEXT)');
        $base->exec('CREATE TABLE CT_EVENT (event_id INTEGER PRIMARY KEY, dept_id INTEGER, event_cat_id INTEGER,
            day_of_week INTEGER, start_time TEXT, end_time TEXT, weeks TEXT, date_change TEXT, notes TEXT)');
        foreach (['MODULE', 'STAFF', 'GROUP'] as $vue) {
            $base->exec(sprintf('CREATE TABLE CT_VIEW_EVENT_%s001 (eid INTEGER, resourcecode TEXT, resourcename TEXT)', $vue));
        }
        $base->exec('CREATE TABLE CT_VIEW_EVENT_ROOM001 (eid INTEGER, resourcecode TEXT, resourcename TEXT, resourceweeks TEXT)');

        $base->exec("INSERT INTO CT_DEPT VALUES (".self::DEPARTEMENT.", 'MMI Troyes')");
    }

    /**
     * La rentrée tombe la semaine du 1er septembre : avant la mi-août, on est encore dans
     * l'année commencée l'automne précédent.
     */
    private function lundiDeLaRentree(\DateTimeImmutable $aujourdhui): \DateTimeImmutable
    {
        $annee = (int) $aujourdhui->format('n') >= 8 && (int) $aujourdhui->format('j') >= 15 || (int) $aujourdhui->format('n') > 8
            ? (int) $aujourdhui->format('Y')
            : (int) $aujourdhui->format('Y') - 1;

        return (new \DateTimeImmutable($annee.'-09-01'))->modify('monday this week');
    }

    private function remplirSemaines(\PDO $base, \DateTimeImmutable $rentree): void
    {
        $insertion = $base->prepare('INSERT INTO CT_WEEK_CONFIG VALUES (?, ?)');
        for ($semaine = 0; $semaine < 43; ++$semaine) {
            $insertion->execute([$semaine, $rentree->modify('+'.$semaine.' weeks')->format('Y-m-d 00:00:00')]);
        }
    }

    private function remplirEvenements(\PDO $base): int
    {
        $categories = [];
        $insertionCategorie = $base->prepare('INSERT INTO CT_EVENT_CAT VALUES (?, ?)');
        foreach (['[CM]', '[TD]', '[TP]', 'Réunion'] as $index => $nom) {
            $insertionCategorie->execute([$index + 1, $nom]);
            $categories[$nom] = $index + 1;
        }

        $masque = $this->masqueDuSemestre();
        $evenement = 1000;
        $modifie = (new \DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s');

        foreach (self::SEMAINE_TYPE as [$jour, $debut, $fin, $categorie, $matiere, $enseignant, $salle, $groupes]) {
            ++$evenement;
            $this->inserer($base, $evenement, $categories[$categorie], $jour, $debut, $fin, $masque, $modifie, null);
            $this->ressource($base, 'MODULE', $evenement, $matiere, self::MATIERES[$matiere]);
            $this->ressource($base, 'STAFF', $evenement, $enseignant, self::ENSEIGNANTS[$enseignant]);
            $base->prepare('INSERT INTO CT_VIEW_EVENT_ROOM001 VALUES (?, ?, ?, NULL)')
                ->execute([$evenement, $salle, self::SALLES[$salle]]);
            foreach ($groupes as $groupe) {
                $this->ressource($base, 'GROUP', $evenement, $groupe, self::GROUPES[$groupe]);
            }
        }

        // Un événement sans matière, pour exercer le cas « hors cours » de la conversion.
        ++$evenement;
        $this->inserer($base, $evenement, $categories['Réunion'], 0, '16:00', '17:00', 'Y', $modifie, 'de rentrée');
        $this->ressource($base, 'GROUP', $evenement, 'MMICM', self::GROUPES['MMICM']);
        $base->prepare('INSERT INTO CT_VIEW_EVENT_ROOM001 VALUES (?, ?, ?, NULL)')
            ->execute([$evenement, 'AMPHI', self::SALLES['AMPHI']]);

        return $evenement - 1000;
    }

    private function masqueDuSemestre(): string
    {
        $masque = '';
        for ($semaine = 0; $semaine < self::SEMAINES_DU_SEMESTRE; ++$semaine) {
            $masque .= \in_array($semaine, self::VACANCES, true) ? 'N' : 'Y';
        }

        return $masque;
    }

    private function inserer(\PDO $base, int $id, int $categorie, int $jour, string $debut, string $fin, string $masque, string $modifie, ?string $notes): void
    {
        // Celcat range les heures dans une date fictive, 30 décembre 1899.
        $base->prepare('INSERT INTO CT_EVENT VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $id, self::DEPARTEMENT, $categorie, $jour,
            '1899-12-30 '.$debut.':00', '1899-12-30 '.$fin.':00',
            $masque, $modifie, $notes,
        ]);
    }

    private function ressource(\PDO $base, string $vue, int $evenement, string $code, string $nom): void
    {
        $base->prepare(sprintf('INSERT INTO CT_VIEW_EVENT_%s001 VALUES (?, ?, ?)', $vue))->execute([$evenement, $code, $nom]);
    }
}
