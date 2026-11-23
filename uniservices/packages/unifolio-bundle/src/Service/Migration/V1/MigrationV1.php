<?php

namespace UnifolioBundle\Service\Migration\V1;

use App\Entity\Apc\ApcNiveau;
use App\Entity\Etudiant\EtudiantScolarite;
use App\Entity\Structure\StructureAnneeUniversitaire;
use App\Entity\Structure\StructureTypeDiplome;
use App\Entity\Users\Etudiant;
use App\Entity\Users\Personnel;
use App\Utils\LooseValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use UnifolioBundle\Entity\Campagne;
use UnifolioBundle\Entity\Commentaire;
use UnifolioBundle\Entity\Cv;
use UnifolioBundle\Entity\Evaluation;
use UnifolioBundle\Entity\Grille;
use UnifolioBundle\Entity\GrilleVersion;
use UnifolioBundle\Entity\MigrationV1Lien;
use UnifolioBundle\Entity\Portfolio;
use UnifolioBundle\Entity\Trace;
use UnifolioBundle\Entity\TraceMedia;
use UnifolioBundle\Entity\TraceRattachement;
use UnifolioBundle\Enum\ConceptionIaEnum;
use UnifolioBundle\Enum\GranulariteEnum;
use UnifolioBundle\Enum\RealisationIaEnum;
use UnifolioBundle\Enum\StatutEvaluationEnum;
use UnifolioBundle\Enum\StatutTraceEnum;
use UnifolioBundle\Enum\TypeCritereEnum;
use UnifolioBundle\Enum\TypeMediaEnum;
use UnifolioBundle\Enum\TypePortfolioEnum;
use UnifolioBundle\Enum\VisibilitePortfolioEnum;
use UnifolioBundle\Repository\PortfolioRepository;
use UnifolioBundle\Repository\TraceRepository;
use UnifolioBundle\Service\Campagne\CampagneResolver;
use UnifolioBundle\Service\Media\EmbedPolicy;
use UnifolioBundle\Service\Media\MediaStorageInterface;
use UnifolioBundle\Service\Media\MediaValidationException;
use UnifolioBundle\Service\Perimetre\UnifolioPerimetre;
use UnifolioBundle\Service\Portfolio\BlocValidator;
use UnifolioBundle\Service\Portfolio\ContexteEtudiant;
use UnifolioBundle\Service\Portfolio\CvManager;
use UnifolioBundle\Service\Portfolio\ModeleParDefaut;
use UnifolioBundle\Service\Referentiel\ReferentielAnnee;
use UnifolioBundle\Service\Trace\UnifolioValidationException;

/**
 * Reprise des données UniFolio v1 (plan § 7). Rejouable : un objet déjà migré (table de correspondance) n'est pas recréé.
 * Aucune donnée n'est ignorée sans figurer au rapport. Les écritures se font dans une seule transaction (annulée en --dry-run) ;
 * les fichiers copiés pendant un essai ou une annulation sont supprimés.
 */
class MigrationV1
{
    public const ETAPES = ['traces', 'evaluations', 'commentaires', 'portfolios', 'cv'];
    private const LIBELLE_GRILLE = 'Héritage UniFolio v1';
    private const ECHELONS = [1 => ['Non acquis', 0.0, '#b91c1c'], 2 => ['En cours d\'acquisition', 1.0, '#d97706'], 3 => ['Acquis', 2.0, '#16a34a']];

    private V1Reader $v1;
    private RapportMigration $rapport;
    private string $dossier = '';
    /** @var array<int, array<string, mixed>> */
    private array $v1Etudiants = [];
    /** @var array<int, array<string, mixed>> */
    private array $v1Enseignants = [];
    /** @var array<int, array<string, mixed>> */
    private array $v1AU = [];
    /** @var array<int, array<string, mixed>> */
    private array $v1Annees = [];
    /** @var array<int, array<string, mixed>> */
    private array $v1Niveaux = [];
    /** @var array<int, Etudiant|null> */
    private array $etudiants = [];
    /** @var array<int, Personnel|null> */
    private array $enseignants = [];
    /** @var array<int, (array<ApcNiveau>|null)> clé : id scolarité résolu pour une trace */
    private array $niveauxAnnee = [];
    /** @var list<string> */
    private array $fichiersCrees = [];
    /** @var array<string, ?Campagne> */
    private array $campagnes = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PortfolioRepository $portfolios,
        private readonly TraceRepository $traces,
        private readonly UnifolioPerimetre $perimetre,
        private readonly ReferentielAnnee $referentiel,
        private readonly CampagneResolver $campagneResolver,
        private readonly MediaStorageInterface $medias,
        private readonly BlocValidator $validateur,
        private readonly CvManager $cvManager,
    ) {
    }

    /**
     * @param list<string> $etapes sous-ensemble de self::ETAPES
     */
    public function executer(V1Reader $v1, string $dossierFichiers, array $etapes, RapportMigration $rapport, bool $essai, ?int $limite = null): void
    {
        $this->v1 = $v1;
        $this->rapport = $rapport;
        $this->dossier = rtrim($dossierFichiers, '/');
        $this->fichiersCrees = [];
        $this->etudiants = $this->enseignants = $this->niveauxAnnee = $this->campagnes = [];
        $this->v1Etudiants = $v1->etudiants();
        $this->v1Enseignants = $v1->enseignants();
        $this->v1AU = $v1->anneesUniversitaires();
        $this->v1Annees = $v1->annees();
        $this->v1Niveaux = $v1->niveaux();

        $ids = $limite !== null ? array_slice(array_keys($this->v1Etudiants), 0, $limite) : null;
        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();
        try {
            foreach (self::ETAPES as $etape) {
                if (!\in_array($etape, $etapes, true)) {
                    continue;
                }
                match ($etape) {
                    'traces' => $this->traces($ids),
                    'evaluations' => $this->evaluations(),
                    'commentaires' => $this->commentaires(),
                    'portfolios' => $this->portfolios($ids),
                    'cv' => $this->cvs($ids),
                };
                $this->em->flush();
            }
            if ($essai) {
                $connexion->rollBack();
                $this->supprimerFichiers();
            } else {
                $connexion->commit();
            }
        } catch (\Throwable $e) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }
            $this->supprimerFichiers();
            $this->em->clear();
            throw $e;
        }
        $this->em->clear();
    }

    /** Supprime tout ce que la migration a créé (traces, portfolios, CV) et ses correspondances. Les grilles et campagnes héritées sont conservées. */
    public function purger(RapportMigration $rapport): void
    {
        $c = $this->em->getConnection();
        $c->beginTransaction();
        try {
            foreach ($c->fetchAllAssociative("SELECT v1_id, cible_id FROM unifolio_migration_v1 WHERE type = 'trace'") as $l) {
                foreach ($c->fetchFirstColumn('SELECT chemin FROM unifolio_trace_media WHERE trace_id = ? AND chemin IS NOT NULL', [$l['cible_id']]) as $chemin) {
                    $this->medias->delete(LooseValue::castString($chemin));
                }
            }
            foreach (['trace' => 'unifolio_trace', 'portfolio' => 'unifolio_portfolio', 'cv' => 'unifolio_cv'] as $type => $table) {
                $n = $c->executeStatement("DELETE FROM $table WHERE id IN (SELECT cible_id FROM unifolio_migration_v1 WHERE type = ?)", [$type]);
                $rapport->ecart('info', $type, null, sprintf('%d objet(s) supprimé(s).', $n));
            }
            $c->executeStatement('DELETE FROM unifolio_migration_v1');
            $c->commit();
        } catch (\Throwable $e) {
            $c->rollBack();
            throw $e;
        }
    }

    // ============================================================ Traces

    /** @param list<int>|null $ids */
    private function traces(?array $ids): void
    {
        $validations = [];
        foreach ($this->v1->validations() as $v) {
            $validations[LooseValue::castInt($v['trace_id'])][] = $v;
        }

        foreach ($this->v1->traces($ids) as $t) {
            $id = LooseValue::castInt($t['id']);
            if ($this->lien('trace', $id) !== null) {
                $this->rapport->compter('trace', RapportMigration::DEJA);
                continue;
            }
            $etudiant = $this->etudiant(LooseValue::castInt($t['etudiant_id']));
            if ($etudiant === null) {
                $this->rapport->compter('trace', RapportMigration::IGNORE);
                continue;
            }
            $scolarite = $this->scolarite($etudiant, LooseValue::castInt($t['annee_universitaire_id']), LooseValue::castInt($t['annee_id']), 'trace', $id);

            $trace = new Trace($etudiant, $this->titre($t, $id), $scolarite);
            $trace->setContexte($this->contexte($t));
            $trace->setConceptionIa(ConceptionIaEnum::tryFrom(LooseValue::castInt($t['conception_ia'] ?? 3)) ?? ConceptionIaEnum::NON_RENSEIGNE);
            $trace->setRealisationIa(RealisationIaEnum::tryFrom(LooseValue::castInt($t['realisation_ia'] ?? 4)) ?? RealisationIaEnum::NON_RENSEIGNE);
            if (($t['date_realisation'] ?? null) !== null && $t['date_realisation'] !== '') {
                $trace->setDateRealisation(new \DateTimeImmutable(LooseValue::castString($t['date_realisation'])));
            }
            $this->dates($trace, $t['date_creation'] ?? null, $t['date_modification'] ?? null);

            $nbMedias = $this->medias($trace, $t, $id);
            if ($nbMedias === 0) {
                $this->rapport->ecart('avertissement', 'trace', $id, 'Aucune preuve exploitable : la trace est reprise sans média.');
            }
            if (!$trace->getConceptionIa()->isRenseigne() || !$trace->getRealisationIa()->isRenseigne()) {
                $this->rapport->ecart('info', 'trace', $id, 'Usage de l\'IA non renseigné dans la v1 : à compléter par l\'étudiant.');
            }

            $rattaches = [];
            foreach ($validations[$id] ?? [] as $v) {
                $niveau = $scolarite !== null ? $this->niveau($scolarite, LooseValue::castInt($v['apc_niveau_id']), 'validation', LooseValue::castInt($v['id'])) : null;
                if ($niveau === null) {
                    if ($scolarite === null) {
                        $this->rapport->ecart('avertissement', 'validation', LooseValue::castInt($v['id']), 'Année de la trace non résolue : rattachement impossible.');
                    }
                    continue;
                }
                if (isset($rattaches[$niveau->getId()])) {
                    continue; // deux validations du même niveau sur une trace : un seul rattachement
                }
                $r = TraceRattachement::versNiveau($trace, $niveau);
                $trace->addRattachement($r);
                $rattaches[$niveau->getId()] = $r;
            }

            $this->em->persist($trace);
            $this->em->flush();
            // Les traces v1 sont toutes « déposées » (la v1 n'a pas de brouillon). Pas de contrôle de dépôt : l'historique prime.
            $trace->changerStatut(StatutTraceEnum::DEPOSEE);
            $this->lier('trace', $id, (int) $trace->getId());
            foreach ($validations[$id] ?? [] as $v) {
                $niveau = $scolarite !== null ? $this->niveauSilencieux($scolarite, LooseValue::castInt($v['apc_niveau_id'])) : null;
                if ($niveau !== null && isset($rattaches[$niveau->getId()])) {
                    $this->lier('validation', LooseValue::castInt($v['id']), (int) $rattaches[$niveau->getId()]->getId());
                }
            }
            $this->rapport->compter('trace', RapportMigration::CREE);
        }
    }

    /** @param array<string, mixed> $t */
    private function titre(array $t, int $id): string
    {
        $titre = trim(LooseValue::castString($t['titre'] ?? ''));

        return $titre !== '' ? mb_substr($titre, 0, 255) : sprintf('Trace sans titre (v1 n°%d)', $id);
    }

    /** description + contexte v1 fusionnés en un seul texte (la v2 n'a qu'un champ), HTML retiré. */
    /** @param array<string, mixed> $t */
    private function contexte(array $t): ?string
    {
        $morceaux = array_filter([
            self::texte($t['description'] ?? null),
            self::texte($t['contexte'] ?? null) !== null ? 'Contexte : '.self::texte($t['contexte']) : null,
        ], static fn (?string $morceau): bool => $morceau !== null && $morceau !== '');

        return $morceaux === [] ? null : mb_substr(implode("\n\n", $morceaux), 0, 10000);
    }

    /** @return int nombre de médias créés */
    /** @param array<string, mixed> $t */
    private function medias(Trace $trace, array $t, int $id): int
    {
        $contenu = $t['contenu'] ?? null;
        $elements = \is_string($contenu) ? (json_decode($contenu, true) ?? []) : (\is_array($contenu) ? $contenu : []);
        if (!\is_array($elements)) {
            $this->rapport->ecart('erreur', 'trace', $id, 'Contenu illisible (JSON invalide).');

            return 0;
        }
        $type = LooseValue::castString($t['type_trace'] ?? '');
        $n = 0;
        foreach ($elements as $element) {
            if (!\is_string($element) || trim($element) === '') {
                continue;
            }
            $media = match ($type) {
                'TraceTypeImage' => $this->mediaFichier($trace, TypeMediaEnum::IMAGE, $element, $id),
                'TraceTypePdf' => $this->mediaFichier($trace, TypeMediaEnum::PDF, $element, $id),
                'TraceTypeVideo', 'TraceTypeLien' => $this->mediaLien($trace, $type, $element, $id),
                default => null,
            };
            if ($media === null && !\in_array($type, ['TraceTypeImage', 'TraceTypePdf', 'TraceTypeVideo', 'TraceTypeLien'], true)) {
                $this->rapport->ecart('erreur', 'trace', $id, sprintf('Type de trace inconnu : « %s ».', $type));
                break;
            }
            if ($media !== null) {
                $trace->addMedia($media);
                ++$n;
            }
        }
        $legende = trim(LooseValue::castString($t['legende'] ?? ''));
        if ($legende !== '' && $n > 0) {
            $media = $trace->getMedias()->first();
            if ($media instanceof TraceMedia) {
                $media->setLegende($legende);
            }
        }

        return $n;
    }

    private function mediaFichier(Trace $trace, TypeMediaEnum $type, string $chemin, int $id): ?TraceMedia
    {
        // Jamais de chemin issu de la base : seul le nom de fichier est conservé, dans le dossier v1 indiqué.
        $nom = basename(str_replace('\\', '/', $chemin));
        $source = $this->dossier.'/'.$nom;
        if ($nom === '' || $nom === '.' || $nom === '..' || !is_file($source) || is_link($source)) {
            $this->rapport->ecart('avertissement', 'trace', $id, sprintf('Fichier introuvable : %s', $nom));

            return null;
        }
        // Copie temporaire : la v1 reste intacte (le stockage déplace le fichier qu'on lui confie).
        $tmp = sys_get_temp_dir().'/unifolio-v1-'.bin2hex(random_bytes(6)).'-'.$nom;
        if (!copy($source, $tmp)) {
            $this->rapport->ecart('erreur', 'trace', $id, sprintf('Copie impossible : %s', $nom));

            return null;
        }
        try {
            $stocke = $this->medias->store(new UploadedFile($tmp, $nom, null, null, true), $type);
            $this->fichiersCrees[] = $stocke->chemin;

            return TraceMedia::depuisFichier($trace, $type, $stocke);
        } catch (MediaValidationException $e) {
            $this->rapport->ecart('avertissement', 'trace', $id, sprintf('Fichier refusé (%s) : %s', $nom, $e->getMessage()));

            return null;
        } finally {
            @unlink($tmp);
        }
    }

    private function mediaLien(Trace $trace, string $type, string $url, int $id): ?TraceMedia
    {
        $url = trim($url);
        if (preg_match('#^https?://#i', $url) !== 1 || filter_var($url, \FILTER_VALIDATE_URL) === false) {
            $this->rapport->ecart('avertissement', 'trace', $id, sprintf('Lien invalide ignoré : %s', mb_substr($url, 0, 120)));

            return null;
        }
        try {
            $embed = $type === 'TraceTypeVideo' && EmbedPolicy::isAutorise($url);

            return TraceMedia::depuisUrl($trace, $embed ? TypeMediaEnum::EMBED : TypeMediaEnum::LIEN, $url);
        } catch (\InvalidArgumentException $e) {
            $this->rapport->ecart('avertissement', 'trace', $id, $e->getMessage());

            return null;
        }
    }

    // ============================================================ Évaluations

    private function evaluations(): void
    {
        // Plusieurs validations d'un même enseignant sur un même niveau d'une même trace : seule la plus récente subsiste en v2.
        $validations = $this->v1->validations();
        $derniere = [];
        foreach ($validations as $v) {
            $r = $this->lien('validation', LooseValue::castInt($v['id']));
            $cle = $r.'-'.LooseValue::castInt($v['enseignant_id'] ?? 0);
            if ($r !== null && (!isset($derniere[$cle]) || LooseValue::castString($v['date_modification'] ?? '').sprintf('%010d', LooseValue::castInt($v['id'])) > $derniere[$cle][0])) {
                $derniere[$cle] = [LooseValue::castString($v['date_modification'] ?? '').sprintf('%010d', LooseValue::castInt($v['id'])), LooseValue::castInt($v['id'])];
            }
        }
        foreach ($validations as $v) {
            $id = LooseValue::castInt($v['id']);
            $etat = LooseValue::castInt($v['etat'] ?? 0);
            $cleRattachement = $this->lien('validation', $id);
            if ($cleRattachement !== null && ($derniere[$cleRattachement.'-'.LooseValue::castInt($v['enseignant_id'] ?? 0)][1] ?? $id) !== $id && $etat !== 0) {
                $this->rapport->compter('evaluation', 'remplacée (plus récente)');
                $this->rapport->ecart('info', 'evaluation', $id, 'Validation remplacée par une validation plus récente du même enseignant sur le même niveau.');
                continue;
            }
            if ($etat === 0) {
                $this->rapport->compter('evaluation', 'en attente (non reprise)');
                continue;
            }
            if ($this->lien('evaluation', $id) !== null) {
                $this->rapport->compter('evaluation', RapportMigration::DEJA);
                continue;
            }
            $rattachementId = $this->lien('validation', $id);
            if ($rattachementId === null) {
                $this->rapport->compter('evaluation', RapportMigration::IGNORE);
                $this->rapport->ecart('avertissement', 'evaluation', $id, 'Validation sans rattachement migré (trace ou niveau non résolu).');
                continue;
            }
            $evaluateur = $v['enseignant_id'] !== null ? $this->enseignant(LooseValue::castInt($v['enseignant_id'])) : null;
            if ($evaluateur === null) {
                $this->rapport->compter('evaluation', RapportMigration::IGNORE);
                $this->rapport->ecart('avertissement', 'evaluation', $id, 'Enseignant introuvable dans uniServices : évaluation non reprise.');
                continue;
            }
            $rattachement = $this->em->find(TraceRattachement::class, $rattachementId);
            $scolarite = $rattachement?->getTrace()->getScolarite();
            if ($rattachement === null || $scolarite === null) {
                $this->rapport->compter('evaluation', RapportMigration::IGNORE);
                continue;
            }
            $campagne = $this->campagne($scolarite, $id);
            $echelon = self::ECHELONS[$etat] ?? null;
            if ($campagne === null || $echelon === null) {
                $this->rapport->compter('evaluation', RapportMigration::IGNORE);
                if ($echelon === null) {
                    $this->rapport->ecart('avertissement', 'evaluation', $id, sprintf('État de validation inconnu : %d.', $etat));
                }
                continue;
            }
            $version = $this->grilleHeritage($scolarite);
            $critere = $version->getCriteres()->first();
            $niveau = null;
            foreach ($version->getEchelons() as $e) {
                if ($e->getLibelle() === $echelon[0]) {
                    $niveau = $e;
                }
            }

            try {
                $evaluation = Evaluation::pourRattachement($campagne, $evaluateur, $rattachement, $scolarite, $version);
                if ($critere === false || $niveau === null) {
                    throw new \DomainException('Critère ou échelon de grille introuvable.');
                }
                $evaluation->renseigner(['appreciation' => null, 'reponses' => [$critere->getId() => ['echelon' => $niveau->getId()]]]);
                $evaluation->rendre(true, $this->date($v['date_modification'] ?? $v['date_creation'] ?? null));
            } catch (\InvalidArgumentException|\DomainException $e) {
                $this->rapport->compter('evaluation', RapportMigration::IGNORE);
                $this->rapport->ecart('erreur', 'evaluation', $id, $e->getMessage());
                continue;
            }
            $this->em->persist($evaluation);
            $this->em->flush();
            $this->lier('evaluation', $id, (int) $evaluation->getId());
            $this->rapport->compter('evaluation', RapportMigration::CREE);
        }

        // Statut des traces : « évaluée » quand tous leurs rattachements portent une évaluation rendue.
        foreach ($this->em->getConnection()->fetchFirstColumn("SELECT cible_id FROM unifolio_migration_v1 WHERE type = 'trace'") as $traceId) {
            $trace = $this->em->find(Trace::class, $traceId);
            if ($trace === null || $trace->getStatut() !== StatutTraceEnum::DEPOSEE || $trace->getRattachements()->isEmpty()) {
                continue;
            }
            $toutes = true;
            foreach ($trace->getRattachements() as $r) {
                $n = LooseValue::castInt($this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM unifolio_evaluation WHERE rattachement_id = ? AND statut = 'rendue'", [$r->getId()]));
                $toutes = $toutes && $n > 0;
            }
            if ($toutes) {
                try {
                    $trace->changerStatut(StatutTraceEnum::EN_EVALUATION);
                    $trace->changerStatut(StatutTraceEnum::EVALUEE);
                } catch (\DomainException $e) {
                    $this->rapport->ecart('avertissement', 'trace', (int) $trace->getId(), 'Statut « évaluée » impossible : '.$e->getMessage());
                }
            }
        }
    }

    /** Grille inactive « Héritage UniFolio v1 » (niveau, 3 échelons) : sert uniquement à porter les évaluations reprises. */
    private function grilleHeritage(EtudiantScolarite $scolarite): GrilleVersion
    {
        $type = $this->perimetre->getDiplome($scolarite)?->getTypeDiplome();
        \assert($type instanceof StructureTypeDiplome);
        $grille = $this->em->getRepository(Grille::class)->findOneBy(['libelle' => self::LIBELLE_GRILLE, 'typeDiplome' => $type]);
        if ($grille === null) {
            $grille = new Grille(self::LIBELLE_GRILLE, $type, GranulariteEnum::NIVEAU);
            $grille->setDescription('Évaluations reprises d\'UniFolio v1 (acquis / en cours d\'acquisition / non acquis). Grille archivée, non proposée aux campagnes.');
            $grille->setActif(false);
            $version = $grille->creerBrouillon();
            $version->viderContenu(); // la version neuve reçoit une échelle par défaut : on la remplace
            foreach (self::ECHELONS as [$libelle, $valeur, $couleur]) {
                $version->ajouterEchelon($libelle, $valeur, $couleur);
            }
            $version->ajouterCritere('Niveau d\'acquisition (UniFolio v1)', TypeCritereEnum::ECHELLE)->setPoids(1)->setObligatoire(true);
            $version->publier();
            $this->em->persist($grille);
            $this->em->flush();
        }

        return $grille->getVersionPubliee() ?? throw new \RuntimeException('La grille d\'héritage n\'a pas de version publiée.');
    }

    /**
     * Campagne de la scolarité ; à défaut, et seulement pour une année universitaire révolue, une campagne « héritée »
     * clôturée (les évaluations doivent être rattachées à une campagne). Année en cours sans campagne : aucune création.
     */
    private function campagne(EtudiantScolarite $scolarite, int $validationId): ?Campagne
    {
        $existante = $this->campagneResolver->pourScolarite($scolarite);
        if ($existante !== null) {
            return $existante;
        }
        $au = $scolarite->getAnneeUniversitaire();
        $diplome = $this->perimetre->getDiplome($scolarite);
        $annee = $this->perimetre->getAnneeFormation($scolarite);
        $courante = $this->em->getRepository(StructureAnneeUniversitaire::class)->findOneBy(['actif' => true]);
        if ($au === null || $diplome === null || $courante === null || $au->getAnnee() >= $courante->getAnnee()) {
            $this->rapport->ecart('avertissement', 'evaluation', $validationId, 'Pas de campagne pour cette année (en cours ou inconnue) : évaluation non reprise. Créez la campagne puis relancez l\'étape.');

            return null;
        }
        $cle = $diplome->getId().'-'.$au->getId().'-'.($annee?->getId() ?? 0);
        if (!\array_key_exists($cle, $this->campagnes)) {
            $debut = new \DateTimeImmutable(sprintf('%d-09-01', $au->getAnnee()));
            $fin = new \DateTimeImmutable(sprintf('%d-08-31', ($au->getAnnee() ?? 0) + 1));
            $c = new Campagne(sprintf('Héritage UniFolio v1 — %s — %s', $diplome->getLibelle() ?? 'Formation', $au->getLibelle() ?? $au->getAnnee()), $diplome, $au, $debut);
            $c->setAnneeFormation($annee);
            $c->definirCalendrier($debut, null, null, $fin);
            $c->cloturer($fin);
            $this->em->persist($c);
            $this->em->flush();
            $this->campagnes[$cle] = $c;
            $this->rapport->compter('campagne héritée', RapportMigration::CREE);
        }

        return $this->campagnes[$cle];
    }

    // ============================================================ Commentaires

    private function commentaires(): void
    {
        $crees = [];
        foreach ($this->v1->commentaires() as $c) {
            $c = LooseValue::assoc($c);
            $id = LooseValue::castInt($c['id']);
            if ($c['trace_id'] === null) {
                $this->rapport->compter('commentaire', RapportMigration::IGNORE);
                $this->rapport->ecart('info', 'commentaire', $id, 'Commentaire de portfolio : la v2 ne commente que les traces, non repris.');
                continue;
            }
            if ($this->lien('commentaire', $id) !== null) {
                $this->rapport->compter('commentaire', RapportMigration::DEJA);
                continue;
            }
            $traceId = $this->lien('trace', LooseValue::castInt($c['trace_id']));
            $trace = $traceId !== null ? $this->em->find(Trace::class, $traceId) : null;
            $contenu = self::texte($c['contenu'] ?? null);
            if ($trace === null || $contenu === null) {
                $this->rapport->compter('commentaire', RapportMigration::IGNORE);
                $this->rapport->ecart('avertissement', 'commentaire', $id, $trace === null ? 'Trace non migrée.' : 'Commentaire vide.');
                continue;
            }
            if (mb_strlen($contenu) > Commentaire::LONGUEUR_MAX) {
                $contenu = mb_substr($contenu, 0, Commentaire::LONGUEUR_MAX);
                $this->rapport->ecart('info', 'commentaire', $id, 'Commentaire tronqué à la longueur maximale.');
            }
            $parent = isset($c['commentaire_parent'], $crees[LooseValue::castInt($c['commentaire_parent'])]) ? $crees[LooseValue::castInt($c['commentaire_parent'])] : null;
            $auteur = $c['enseignant_id'] !== null ? $this->enseignant(LooseValue::castInt($c['enseignant_id'])) : null;
            try {
                $commentaire = $auteur !== null
                    ? Commentaire::parPersonnel($trace, $auteur, $contenu, null, $parent)
                    : Commentaire::parEtudiant($trace, $trace->getEtudiant(), $contenu, null, $parent);
            } catch (\InvalidArgumentException $e) {
                $this->rapport->compter('commentaire', RapportMigration::IGNORE);
                $this->rapport->ecart('erreur', 'commentaire', $id, $e->getMessage());
                continue;
            }
            $this->dates($commentaire, $c['date_creation'] ?? null, $c['date_modification'] ?? null);
            $this->em->persist($commentaire);
            $this->em->flush();
            $crees[$id] = $commentaire;
            $this->lier('commentaire', $id, (int) $commentaire->getId());
            $this->rapport->compter('commentaire', RapportMigration::CREE);
        }
    }

    // ============================================================ Portfolios

    /** @param list<int>|null $ids */
    private function portfolios(?array $ids): void
    {
        foreach ($this->v1->portfolios($ids) as $p) {
            $p = LooseValue::assoc($p);
            $id = LooseValue::castInt($p['id']);
            if ($this->lien('portfolio', $id) !== null) {
                $this->rapport->compter('portfolio', RapportMigration::DEJA);
                continue;
            }
            $etudiant = $this->etudiant(LooseValue::castInt($p['etudiant_id']));
            $scolarite = $etudiant !== null ? $this->scolariteDeLAnnee($etudiant, LooseValue::castInt($p['annee_id'] ?? 0)) : null;
            if ($etudiant === null || $scolarite === null) {
                $this->rapport->compter('portfolio', RapportMigration::IGNORE);
                if ($etudiant !== null) {
                    $this->rapport->ecart('avertissement', 'portfolio', $id, 'Année de BUT du portfolio non retrouvée dans le parcours de l\'étudiant : non repris.');
                }
                continue;
            }

            $existant = $this->portfolios->findAcademique($etudiant, $scolarite);
            $intitule = trim(LooseValue::castString($p['intitule'] ?? ''));
            $portfolio = $existant ?? new Portfolio($etudiant, TypePortfolioEnum::ACADEMIQUE, mb_substr($intitule !== '' ? $intitule : 'Portfolio', 0, 255), $scolarite);
            $prefixe = $existant !== null ? 'Archive v1 — ' : '';
            if ($existant !== null) {
                $this->rapport->ecart('info', 'portfolio', $id, 'Un portfolio académique existe déjà pour cette année : les pages v1 y sont ajoutées (préfixe « Archive v1 »).');
            }

            $contexte = new ContexteEtudiant($etudiant, $this->traces);
            try {
                $nbPages = 0;
                foreach ($this->v1->pagesDuPortfolio($id) as $page) {
                    $intitulePage = trim(LooseValue::castString($page['intitule'] ?? ''));
                    $blocs = [ModeleParDefaut::bloc('heading', ['niveau' => 2, 'texte' => mb_substr($intitulePage !== '' ? $intitulePage : 'Page', 0, 200)])];
                    $description = self::texte($page['description'] ?? null);
                    if ($description !== null) {
                        $blocs[] = ModeleParDefaut::bloc('paragraph', ['texte' => mb_substr($description, 0, 5000)]);
                    }
                    foreach ($page['traces'] as $traceV1) {
                        $traceId = $this->lien('trace', $traceV1);
                        $uuid = $traceId !== null ? $this->em->find(Trace::class, $traceId)?->getUuidString() : null;
                        if ($uuid === null) {
                            $this->rapport->ecart('avertissement', 'portfolio', $id, sprintf('Trace v1 n°%d absente de la v2 (non migrée) : retirée de la page.', $traceV1));
                            continue;
                        }
                        $blocs[] = ModeleParDefaut::bloc('trace', ['trace' => $uuid, 'affichage' => 'detail']);
                    }
                    $cible = $portfolio->ajouterPage($prefixe.mb_substr($intitulePage !== '' ? $intitulePage : 'Page', 0, 110));
                    $cible->initialiser($this->validateur->valider($blocs, $contexte), []);
                    ++$nbPages;
                }
                if ($p['cv_id'] !== null) {
                    $portfolio->ajouterPage($prefixe.'CV')->initialiser($this->validateur->valider([ModeleParDefaut::bloc('cv', [])], $contexte), []);
                }
            } catch (\DomainException|UnifolioValidationException $e) {
                $this->rapport->compter('portfolio', RapportMigration::IGNORE);
                $this->rapport->ecart('erreur', 'portfolio', $id, $e->getMessage());
                if ($existant === null) {
                    continue;
                }
            }

            if ($existant === null) {
                if (LooseValue::castInt($p['visibilite'] ?? 0) === 1) {
                    $portfolio->setVisibilite(VisibilitePortfolioEnum::ETABLISSEMENT);
                    $this->rapport->ecart('info', 'portfolio', $id, 'Portfolio visible dans la v1 : repris en visibilité « établissement » (une vitrine publique se crée depuis la v2) ; à republier.');
                }
                $portfolio->setDescription(self::texte($p['description'] ?? null));
                $this->dates($portfolio, $p['date_creation'] ?? null, $p['date_modification'] ?? null);
            }
            if (($p['banniere'] ?? null) !== null && $p['banniere'] !== '' && !str_ends_with(LooseValue::castString($p['banniere']), 'banniere.jpg')) {
                $this->rapport->ecart('info', 'portfolio', $id, 'Bannière personnalisée non reprise (non gérée par la v2).');
            }
            $this->em->persist($portfolio);
            $this->em->flush();
            $this->lier('portfolio', $id, (int) $portfolio->getId());
            $this->rapport->compter('portfolio', RapportMigration::CREE);
        }
    }

    // ============================================================ CV

    /** @param list<int>|null $ids */
    private function cvs(?array $ids): void
    {
        $parEtudiant = [];
        foreach ($this->v1->cvs($ids) as $cv) {
            $cv = LooseValue::assoc($cv);
            $parEtudiant[LooseValue::castInt($cv['etudiant_id'])][] = $cv;
        }
        foreach ($parEtudiant as $etudiantV1 => $liste) {
            $etudiant = $this->etudiant($etudiantV1);
            if ($etudiant === null) {
                $this->rapport->compter('cv', RapportMigration::IGNORE);
                continue;
            }
            // Un seul CV en v2 : le plus récemment modifié.
            usort($liste, static function (array $a, array $b): int {
                $compare = strcmp(LooseValue::castString($b['date_modification'] ?? ''), LooseValue::castString($a['date_modification'] ?? ''));

                return $compare !== 0 ? $compare : LooseValue::castInt($b['id']) <=> LooseValue::castInt($a['id']);
            });
            $retenu = $liste[0];
            if (\count($liste) > 1) {
                $this->rapport->ecart('info', 'cv', LooseValue::castInt($retenu['id']), sprintf('%d CV dans la v1 : seul le plus récent est repris.', \count($liste)));
            }
            if ($this->lien('cv', LooseValue::castInt($retenu['id'])) !== null) {
                $this->rapport->compter('cv', RapportMigration::DEJA);
                continue;
            }
            if ($this->em->getRepository(Cv::class)->findOneBy(['etudiant' => $etudiant]) !== null) {
                $this->rapport->compter('cv', RapportMigration::IGNORE);
                $this->rapport->ecart('info', 'cv', LooseValue::castInt($retenu['id']), 'L\'étudiant a déjà un CV dans la v2 : conservé tel quel.');
                continue;
            }
            try {
                $cvV2 = $this->cvManager->enregistrer($etudiant, $this->documentCv($retenu));
            } catch (UnifolioValidationException $e) {
                $this->rapport->compter('cv', RapportMigration::IGNORE);
                foreach ($e->getErreurs() as $message) {
                    $this->rapport->ecart('erreur', 'cv', LooseValue::castInt($retenu['id']), $message);
                }
                continue;
            }
            $this->lier('cv', LooseValue::castInt($retenu['id']), (int) $cvV2->getId());
            $this->rapport->compter('cv', RapportMigration::CREE);
        }
    }

    /**
     * @param array<string, mixed> $cv
     * @return array<string, mixed> document attendu par CvManager
     */
    private function documentCv(array $cv): array
    {
        $liste = static function (mixed $v): array {
            $v = \is_string($v) ? (json_decode($v, true) ?? []) : $v;

            return \is_array($v) ? $v : [];
        };
        $textes = static fn (array $l): array => array_values(array_filter(array_map(static fn (mixed $x): ?string => \is_scalar($x) ? trim(LooseValue::castString($x)) : null, $l), static fn (?string $x): bool => $x !== null && $x !== ''));
        $langues = array_map(static fn (mixed $l): array => \is_array($l) ? ['langue' => LooseValue::castString($l['langue'] ?? $l['nom'] ?? $l['name'] ?? ''), 'niveau' => $l['niveau'] ?? $l['level'] ?? null] : ['langue' => LooseValue::castString($l), 'niveau' => null], $liste($cv['langues'] ?? []));
        // Réseaux v1 : liste d'adresses ou de paires {nom, url}. Seules les adresses http(s) sont gardées.
        $reseaux = [];
        foreach ($liste($cv['reseaux'] ?? []) as $r) {
            $u = \is_array($r) ? ($r['url'] ?? $r['lien'] ?? null) : $r;
            if (\is_string($u) && preg_match('#^https?://#i', trim($u)) === 1) {
                $type = trim(LooseValue::castString(\is_array($r) ? ($r['type'] ?? $r['nom'] ?? $r['name'] ?? '') : ''));
                $reseaux[] = ['type' => mb_substr($type !== '' ? $type : 'site', 0, 40), 'url' => trim($u)];
            } else {
                $json = json_encode($r, \JSON_UNESCAPED_UNICODE);
                $this->rapport->ecart('avertissement', 'cv', LooseValue::castInt($cv['id']), sprintf('Lien de réseau non repris (adresse http(s) attendue) : %s', mb_substr($json !== false ? $json : '', 0, 100)));
            }
        }
        $activite = static function (mixed $a): string {
            $valeurs = \is_array($a) ? $a : (\is_string($a) ? json_decode($a, true) : []);
            $valeurs = \is_array($valeurs) ? $valeurs : [];

            return implode("\n", array_map(static fn (mixed $x): string => '- '.trim(LooseValue::castString($x)), array_filter($valeurs, static fn (mixed $x): bool => \is_scalar($x) && trim(LooseValue::castString($x)) !== '')));
        };
        $jour = static fn (mixed $d): ?string => $d !== null && $d !== '' && $d !== false ? substr(LooseValue::castString($d), 0, 10) : null;

        $experiencesBrutes = \is_array($cv['experiences'] ?? null) ? $cv['experiences'] : [];
        $formationsBrutes = \is_array($cv['formations'] ?? null) ? $cv['formations'] : [];
        $experiences = array_values(array_filter($experiencesBrutes, static fn (mixed $e): bool => \is_array($e)));
        $formations = array_values(array_filter($formationsBrutes, static fn (mixed $f): bool => \is_array($f)));

        return [
            'poste' => self::texte($cv['poste'] ?? null),
            'description' => self::texte($cv['description'] ?? null),
            'langues' => array_values(array_filter($langues, static fn (array $l) => trim($l['langue']) !== '')),
            'softSkills' => $textes($liste($cv['soft_skills'] ?? [])),
            'hardSkills' => $textes($liste($cv['hard_skills'] ?? [])),
            'reseaux' => $reseaux,
            'experiences' => array_map(static fn (array $e): array => ['poste' => LooseValue::castString($e['poste'] ?? ''), 'entreprise' => $e['entreprise'] ?? null, 'dateDebut' => $jour($e['date_debut'] ?? null), 'dateFin' => $jour($e['date_fin'] ?? null), 'description' => ($description = $activite($e['activite'] ?? null)) !== '' ? $description : null], $experiences),
            'formations' => array_map(static fn (array $f): array => ['diplome' => LooseValue::castString($f['diplome'] ?? ''), 'etablissement' => $f['etablissement'] ?? null, 'dateDebut' => $jour($f['date_debut'] ?? null), 'dateFin' => $jour($f['date_fin'] ?? null), 'description' => ($description = $activite($f['activite'] ?? null)) !== '' ? $description : null], $formations),
        ];
    }

    // ============================================================ Résolution des personnes, années, niveaux

    private function etudiant(int $v1Id): ?Etudiant
    {
        if (\array_key_exists($v1Id, $this->etudiants)) {
            return $this->etudiants[$v1Id];
        }
        $ligne = $this->v1Etudiants[$v1Id] ?? null;
        $etudiant = null;
        $username = '';
        $mail = '';
        if ($ligne !== null) {
            $repo = $this->em->getRepository(Etudiant::class);
            $username = LooseValue::castString($ligne['username'] ?? '');
            $mail = LooseValue::castString($ligne['mail_univ'] ?? '');
            $etudiant = ($username !== '' ? $repo->findOneBy(['username' => $username]) : null)
                ?? ($mail !== '' ? $repo->findOneBy(['mailUniv' => $mail]) : null);
        }
        if ($etudiant === null) {
            // RGPD : un compte v1 absent d'uniServices n'est pas importé ; il figure une fois au rapport.
            $this->rapport->ecart('avertissement', 'etudiant', $v1Id, sprintf('Étudiant introuvable dans uniServices (%s) : ses données ne sont pas reprises.', $username !== '' ? $username : ($mail !== '' ? $mail : 'identifiant inconnu')));
        }

        return $this->etudiants[$v1Id] = $etudiant;
    }

    private function enseignant(int $v1Id): ?Personnel
    {
        if (\array_key_exists($v1Id, $this->enseignants)) {
            return $this->enseignants[$v1Id];
        }
        $ligne = $this->v1Enseignants[$v1Id] ?? null;
        $personnel = null;
        $username = '';
        $mail = '';
        if ($ligne !== null) {
            $repo = $this->em->getRepository(Personnel::class);
            $username = LooseValue::castString($ligne['username'] ?? '');
            $mail = LooseValue::castString($ligne['mail_univ'] ?? '');
            $personnel = ($username !== '' ? $repo->findOneBy(['username' => $username]) : null)
                ?? ($mail !== '' ? $repo->findOneBy(['mailUniv' => $mail]) : null);
        }
        if ($personnel === null) {
            $this->rapport->ecart('avertissement', 'enseignant', $v1Id, sprintf('Enseignant introuvable dans uniServices (%s).', $username !== '' ? $username : ($mail !== '' ? $mail : 'identifiant inconnu')));
        }

        return $this->enseignants[$v1Id] = $personnel;
    }

    /** Scolarité correspondant à (année universitaire v1, année de BUT v1) pour cet étudiant. */
    private function scolarite(Etudiant $etudiant, int $auV1, int $anneeV1, string $type, int $id): ?EtudiantScolarite
    {
        $au = $this->v1AU[$auV1] ?? null;
        $ordre = isset($this->v1Annees[$anneeV1]) ? LooseValue::castInt($this->v1Annees[$anneeV1]['ordre']) : null;
        $candidates = [];
        foreach ($etudiant->getScolarites() as $s) {
            $su = $s->getAnneeUniversitaire();
            if ($su === null || !$this->perimetre->isScolariteEligible($s)) {
                continue;
            }
            if ($au !== null && $su->getAnnee() === LooseValue::castInt($au['annee']) || ($au !== null && $su->getLibelle() !== null && $su->getLibelle() === LooseValue::castString($au['libelle']))) {
                $candidates[] = $s;
            }
        }
        $choisie = null;
        if (\count($candidates) === 1) {
            $choisie = $candidates[0];
        } elseif ($candidates !== [] && $ordre !== null) {
            foreach ($candidates as $s) {
                if ($this->perimetre->getAnneeFormation($s)?->getOrdre() === $ordre) {
                    $choisie = $s;
                }
            }
        }
        if ($choisie === null) {
            $this->rapport->ecart('avertissement', $type, $id, sprintf('Année %s non retrouvée dans le parcours de l\'étudiant.', LooseValue::castString($au['libelle'] ?? '?')));
        }

        return $choisie;
    }

    /** Scolarité la plus récente de l'étudiant pour une année de BUT v1 (les portfolios v1 ne portent pas l'année universitaire). */
    private function scolariteDeLAnnee(Etudiant $etudiant, int $anneeV1): ?EtudiantScolarite
    {
        $ordre = isset($this->v1Annees[$anneeV1]) ? LooseValue::castInt($this->v1Annees[$anneeV1]['ordre']) : null;
        $retenue = null;
        foreach ($etudiant->getScolarites() as $s) {
            if (!$this->perimetre->isScolariteEligible($s) || $this->perimetre->getAnneeFormation($s)?->getOrdre() !== $ordre) {
                continue;
            }
            if ($retenue === null || (int) $s->getAnneeUniversitaire()?->getAnnee() > (int) $retenue->getAnneeUniversitaire()?->getAnnee()) {
                $retenue = $s;
            }
        }

        return $retenue;
    }

    private function niveau(EtudiantScolarite $scolarite, int $niveauV1, string $type, int $id): ?ApcNiveau
    {
        $niveau = $this->niveauSilencieux($scolarite, $niveauV1);
        if ($niveau === null) {
            $this->rapport->ecart('avertissement', $type, $id, sprintf('Niveau v1 n°%d (%s) non résolu dans le référentiel de l\'année.', $niveauV1, LooseValue::castString($this->v1Niveaux[$niveauV1]['nom_court'] ?? '?')));
        }

        return $niveau;
    }

    private function niveauSilencieux(EtudiantScolarite $scolarite, int $niveauV1): ?ApcNiveau
    {
        $v1 = $this->v1Niveaux[$niveauV1] ?? null;
        if ($v1 === null) {
            return null;
        }
        $cle = (int) $scolarite->getId();
        $this->niveauxAnnee[$cle] ??= $this->referentiel->niveaux($scolarite);
        foreach ($this->niveauxAnnee[$cle] as $n) {
            $c = $n->getCompetence();
            $memeCompetence = $c !== null && (
                mb_strtolower(trim(LooseValue::castString($c->getNomCourt()))) === mb_strtolower(trim(LooseValue::castString($v1['nom_court'])))
                || mb_strtolower(trim(LooseValue::castString($c->getLibelle()))) === mb_strtolower(trim(LooseValue::castString($v1['competence_libelle'])))
            );
            if ($memeCompetence && $n->getOrdre() === LooseValue::castInt($v1['ordre'])) {
                return $n;
            }
        }

        return null;
    }

    // ============================================================ Utilitaires

    private function lien(string $type, int $v1Id): ?int
    {
        $cible = $this->em->getConnection()->fetchOne('SELECT cible_id FROM unifolio_migration_v1 WHERE type = ? AND v1_id = ?', [$type, $v1Id]);

        return $cible === false ? null : LooseValue::castInt($cible);
    }

    private function lier(string $type, int $v1Id, int $cibleId): void
    {
        $this->em->getConnection()->executeStatement(
            'INSERT INTO unifolio_migration_v1 (type, v1_id, cible_id, cree_le) VALUES (?, ?, ?, ?)',
            [$type, $v1Id, $cibleId, (new \DateTimeImmutable())->format('Y-m-d H:i:s.u')]
        );
    }

    private function dates(object $entite, mixed $creation, mixed $modification): void
    {
        if (method_exists($entite, 'setCreatedAt') && ($d = $this->date($creation)) !== null) {
            $entite->setCreatedAt($d);
        }
        if (method_exists($entite, 'setUpdatedAt') && ($d = $this->date($modification ?? $creation)) !== null) {
            $entite->setUpdatedAt($d);
        }
    }

    private function date(mixed $valeur): ?\DateTimeImmutable
    {
        try {
            return $valeur !== null && $valeur !== '' && $valeur !== false ? new \DateTimeImmutable(LooseValue::castString($valeur)) : null;
        } catch (\Exception) {
            return null;
        }
    }

    /** Texte brut : HTML retiré, entités décodées, espaces normalisés ; null si vide. */
    public static function texte(mixed $valeur): ?string
    {
        if (!\is_string($valeur) || trim($valeur) === '') {
            return null;
        }
        $texte = preg_replace('#<br\s*/?>|</p>|</li>#i', "\n", $valeur) ?? $valeur;
        $texte = html_entity_decode(strip_tags($texte), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $texte = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $texte)) ?? $texte) ?? $texte);

        return $texte === '' ? null : $texte;
    }

    private function supprimerFichiers(): void
    {
        foreach ($this->fichiersCrees as $chemin) {
            $this->medias->delete($chemin);
        }
        $this->fichiersCrees = [];
    }
}
