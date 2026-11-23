<?php

namespace UnifolioBundle\Service\Publication;

use App\Utils\LooseValue;
use Symfony\Component\String\Slugger\AsciiSlugger;
use UnifolioBundle\Entity\Portfolio;
use UnifolioBundle\Service\Portfolio\BlocValidator;
use UnifolioBundle\Service\Portfolio\PortfolioDonnees;
use UnifolioBundle\Service\Trace\UnifolioValidationException;

/**
 * Construit l'instantané d'un portfolio : tout ce qui sera rendu, et rien d'autre.
 * Minimisation des données : seules les traces effectivement montrées par un bloc sont retenues, avec une liste blanche
 * de champs ; CV, progression et parcours ne sont inclus que si un bloc les affiche. Les médias sont désignés par
 * adresses relatives à la publication (media/…), jamais par les adresses de l'API.
 */
class PublicationInstantane
{
    private const RESERVES = ['index', 'print', 'portfolio'];

    public function __construct(
        private readonly PortfolioDonnees $donnees,
        private readonly BlocValidator $validateur,
        private readonly ThemeNormalizer $themes,
    ) {
    }

    /**
     * @return array{portfolio: array<string, mixed>, pages: list<array<string, mixed>>, donnees: array<string, mixed>}
     *
     * @throws UnifolioValidationException si une page est incomplète ou si le portfolio est vide
     */
    public function construire(Portfolio $portfolio): array
    {
        $pages = [];
        $erreurs = [];
        $slugger = new AsciiSlugger('fr');
        $utilises = [];
        foreach ($portfolio->getPages() as $page) {
            foreach ($this->validateur->incomplets($page->getBlocs(), $page->getTitre()) as $anomalie) {
                $erreurs[] = $anomalie;
            }
            $base = strtolower($slugger->slug($page->getTitre())->toString()) !== '' ? strtolower($slugger->slug($page->getTitre())->toString()) : 'page';
            $fichier = $pages === [] ? 'index' : (\in_array($base, self::RESERVES, true) ? $base.'-page' : $base);
            for ($i = 2; isset($utilises[$fichier]); ++$i) {
                $fichier = $base.'-'.$i;
            }
            $utilises[$fichier] = true;
            $pages[] = ['fichier' => $fichier.'.html', 'titre' => $page->getTitre(), 'blocs' => $page->getBlocs()];
        }
        if ($pages === []) {
            throw UnifolioValidationException::un('Le portfolio ne contient aucune page.');
        }
        if ($erreurs !== []) {
            throw new UnifolioValidationException(array_merge(['Complétez les blocs suivants avant de publier :'], $erreurs));
        }

        $source = $this->donnees->pour($portfolio);
        $sourceTraces = self::liste($source['traces'] ?? []);
        $sourceCv = LooseValue::assoc($source['cv'] ?? []);
        $sourceParcours = self::liste($source['parcours'] ?? []);
        $types = [];
        $uuidsTraces = [];
        foreach ($pages as $p) {
            $this->parcourir(self::liste($p['blocs']), $types, $uuidsTraces, $sourceTraces);
        }

        $etudiant = $portfolio->getEtudiant();

        return [
            'portfolio' => [
                'titre' => $portfolio->getTitre(),
                'description' => $portfolio->getDescription(),
                'auteur' => trim($etudiant->getPrenom().' '.$etudiant->getNom()),
                'indexable' => $portfolio->isIndexable(),
                'theme' => $this->themes->lire($portfolio->getTheme()),
                'logo' => LogoManager::nomPublie($portfolio) !== null
                    ? ['fichier' => LogoManager::nomPublie($portfolio), 'alt' => 'Logo de '.trim($etudiant->getPrenom().' '.$etudiant->getNom())]
                    : null,
            ],
            'pages' => $pages,
            'donnees' => [
                'traces' => $this->traces($sourceTraces, $uuidsTraces),
                'progression' => isset($types['progression']) ? $source['progression'] : [],
                'cv' => isset($types['cv']) ? $this->cv($sourceCv) : $this->cvVide(),
                'parcours' => isset($types['parcours']) ? $this->parcours($sourceParcours) : [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $instantane
     * @return list<array{mediaId: int, destination: string}>
     */
    public static function medias(array $instantane): array
    {
        $liste = [];
        $donnees = LooseValue::assoc($instantane['donnees'] ?? []);
        foreach (self::liste($donnees['traces'] ?? []) as $trace) {
            foreach (self::liste($trace['medias'] ?? []) as $media) {
                if (is_array($media['fichier'] ?? null)) {
                    $fichier = LooseValue::assoc($media['fichier']);
                    if (!isset($fichier['mediaId'], $fichier['url'])) {
                        continue;
                    }
                    $liste[] = ['mediaId' => LooseValue::castInt($fichier['mediaId']), 'destination' => LooseValue::castString($fichier['url'])];
                }
            }
        }

        return $liste;
    }

    /**
     * @param list<array<string, mixed>> $blocs
     * @param array<string, true>        $types
     * @param array<string, true>        $uuids
     * @param list<array<string, mixed>> $traces
     */
    private function parcourir(array $blocs, array &$types, array &$uuids, array $traces): void
    {
        foreach ($blocs as $bloc) {
            $type = LooseValue::castString($bloc['type'] ?? '');
            $attrs = LooseValue::assoc($bloc['attrs'] ?? []);
            $types[$type] = true;
            switch ($type) {
                case 'trace':
                    if (LooseValue::castString($attrs['trace'] ?? '') !== '') {
                        $uuids[strtolower(LooseValue::castString($attrs['trace']))] = true;
                    }
                    break;
                case 'traces':
                    $competence = $attrs['competence'] ?? null;
                    $retenues = array_values(array_filter(
                        $traces,
                        static fn (array $t): bool => $competence === null || array_filter(self::liste($t['competences'] ?? []), static fn (array $c): bool => LooseValue::castInt($c['id']) === LooseValue::castInt($competence)) !== []
                    ));
                    foreach (\array_slice($retenues, 0, LooseValue::castInt($attrs['maximum'] ?? 6)) as $t) {
                        $uuids[strtolower(LooseValue::castString($t['uuid']))] = true;
                    }
                    break;
                case 'image':
                case 'file':
                    $source = LooseValue::assoc($attrs['source'] ?? []);
                    if (LooseValue::castString($source['trace'] ?? '') !== '') {
                        $uuids[strtolower(LooseValue::castString($source['trace']))] = true;
                    }
                    break;
                case 'gallery':
                    foreach (self::liste($attrs['images'] ?? []) as $image) {
                        if (LooseValue::castString($image['trace'] ?? '') !== '') {
                            $uuids[strtolower(LooseValue::castString($image['trace']))] = true;
                        }
                    }
                    break;
            }
            if (($bloc['children'] ?? []) !== []) {
                $this->parcourir(self::liste($bloc['children']), $types, $uuids, $traces);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $traces
     * @param array<string, true>        $retenues
     *
     * @return list<array<string, mixed>>
     */
    private function traces(array $traces, array $retenues): array
    {
        $resultat = [];
        foreach ($traces as $t) {
            if (!isset($retenues[strtolower(LooseValue::castString($t['uuid']))])) {
                continue;
            }
            $court = substr(str_replace('-', '', LooseValue::castString($t['uuid'])), 0, 8);
            $adresses = [];
            $medias = [];
            foreach (self::liste($t['medias'] ?? []) as $m) {
                $fichier = null;
                $fichierSource = is_array($m['fichier'] ?? null) ? LooseValue::assoc($m['fichier']) : [];
                if (LooseValue::castString($fichierSource['url'] ?? '') !== '') {
                    $extension = $this->extension(LooseValue::castString($fichierSource['nom'] ?? ''), LooseValue::castString($fichierSource['mimeType'] ?? ''));
                    $destination = sprintf('media/%s-%d.%s', $court, LooseValue::castInt($m['id']), $extension);
                    $adresses[LooseValue::castString($fichierSource['url'])] = $destination;
                    $fichier = ['url' => $destination, 'nom' => LooseValue::castString($fichierSource['nom'] ?? 'fichier'), 'mediaId' => LooseValue::castInt($m['id'])];
                }
                $medias[] = [
                    'id' => LooseValue::castInt($m['id']),
                    'type' => $m['type'],
                    'icone' => $m['icone'] ?? 'pi pi-file',
                    'legende' => $m['legende'] ?? null,
                    'fichier' => $fichier,
                    'url' => \is_string($m['url'] ?? null) && preg_match('#^https?://#i', $m['url']) === 1 ? $m['url'] : null,
                ];
            }
            $resultat[] = [
                'uuid' => $t['uuid'],
                'titre' => $t['titre'],
                'contexte' => $t['contexte'] ?? null,
                'dateRealisation' => $t['dateRealisation'] ?? null,
                'typeActivite' => is_array($t['typeActivite'] ?? null) && isset($t['typeActivite']['libelle']) ? ['libelle' => LooseValue::castString($t['typeActivite']['libelle'])] : null,
                'vignette' => $adresses[LooseValue::castString($t['vignette'] ?? '')] ?? null,
                'competences' => array_map(static fn (array $c): array => ['id' => $c['id'], 'nomCourt' => $c['nomCourt'], 'couleur' => $c['couleur'] ?? null], self::liste($t['competences'] ?? [])),
                'medias' => $medias,
            ];
        }

        return $resultat;
    }

    /** Extension sûre : liste blanche de formats d'image et de documents courants, sinon .bin (téléchargement). */
    private function extension(string $nom, string $mime): string
    {
        $parMime = [
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif',
            'application/pdf' => 'pdf', 'video/mp4' => 'mp4', 'video/webm' => 'webm', 'audio/mpeg' => 'mp3',
            'application/zip' => 'zip', 'text/plain' => 'txt',
        ];

        return $parMime[strtolower($mime)] ?? 'bin';
    }

    /**
     * @param array<string, mixed> $cv
     * @return array<string, mixed>
     */
    private function cv(array $cv): array
    {
        return array_intersect_key($cv, array_flip(['poste', 'description', 'langues', 'softSkills', 'hardSkills', 'reseaux', 'experiences', 'formations']));
    }

    /** @return array<string, mixed> */
    private function cvVide(): array
    {
        return ['poste' => null, 'description' => null, 'langues' => [], 'softSkills' => [], 'hardSkills' => [], 'reseaux' => [], 'experiences' => [], 'formations' => []];
    }

    /**
     * @param list<array<string, mixed>> $parcours
     *
     * @return list<array<string, mixed>>
     */
    private function parcours(array $parcours): array
    {
        return array_map(static fn (array $p) => ['annee' => $p['annee'] ?? null, 'anneeUniversitaire' => $p['anneeUniversitaire'] ?? null, 'diplome' => $p['diplome'] ?? null, 'actif' => (bool) ($p['actif'] ?? false)], $parcours);
    }

    /** @return list<array<string, mixed>> */
    private static function liste(mixed $value): array
    {
        return array_values(array_map(static fn (mixed $row): array => LooseValue::assoc($row), LooseValue::rows($value)));
    }
}
