# Backlog — espace étudiant

Une fiche par tâche, à recopier dans le kanban. Chaque fiche tient dans une branche et une PR,
comme l'exige `CLAUDE.md`.

## Comment s'en servir

Les tâches sont réparties en colonnes qui suivent les **répertoires**, pas les
fonctionnalités. C'est ce qui permet à trois personnes de travailler en parallèle sans conflit :
prenez une colonne chacun plutôt qu'une fiche au hasard.

Chaque fiche indique les constats d'audit qu'elle couvre. Les identifiants renvoient à
`01-audit-existant.md` (intranet V3) jusqu'à TB-7, DOC-6, RECH-9, MOB-5 et A11Y-6, et à
`05-audit-actuel.md` (uniServices, 24/09/2026) au-delà, ainsi que pour CNX, POR, EDT, SCO,
TROMBI, NAV et DA. Les fiches marquées **[back]** touchent le Symfony, celles marquées
**[back + front]** les deux.

Une fiche **Obsolète** n'a plus d'objet : elle reste pour mémoire, avec sa raison.

Tailles : **S** une session, **M** une journée, **L** plusieurs jours, à redécouper avant de
commencer.

---

## État de la CI

Les trois workflows tournent sur chaque PR vers `develop` et sont **au vert** depuis #76.
**Une PR rouge ne se merge pas** : #72 l'a été, et toutes les PR suivantes ont hérité de son
échec.

| Contrôle | État |
|---|---|
| CI-Packages (front) | vert |
| CI-Back : `composer validate`, `lint:container`, `doctrine:schema:validate` | vert |
| CI-Back : PHPStan | niveau 6, 0 erreur ; 4 identifiants ignorés en bloc dans `phpstan.neon` (E15) |
| CI-Back : PHPUnit | vert |
| CI-Back : style PSR-12 de tout le code PHP (`make cs`, dans `make check`) | vert, depuis E9 |
| CI-Cypress : 9 fichiers, 33 tests, sur une base de fixtures neuve et un Vite froid | vert |

---

## Colonne E — Remettre la CI au vert

### E3 · Corriger la configuration PHPStan · S
**Pourquoi** `phpstan.neon` analyse `packages/edt-bundle/src`, qui n'existe pas : le bundle a
disparu du dépôt. PHPStan s'arrête avant d'analyser quoi que ce soit.
**Où** `uniservices/back/phpstan.neon`
**Terminé quand** `make phpstan` s'exécute jusqu'au bout. Les erreurs qu'il révèle alors font
l'objet d'une fiche à part : on ne les corrige pas dans celle-ci.
**Commencer par là** : c'est la plus courte des trois et elle débloque la mesure du reste.
**Fait** PHPStan s'exécute maintenant jusqu'au bout. Il trouve 271 erreurs, reprises dans E4.

### E1 · Corriger le point d'entrée d'authentification · S
**Pourquoi** `security.yaml` déclare `entry_point: App\Security\LoginFormAuthenticator`, mais
cette classe étend `AbstractAuthenticator` sans implémenter `AuthenticationEntryPointInterface`.
`lint:container` refuse la configuration.
**Où** `uniservices/back/config/packages/security.yaml`, `back/src/Security/LoginFormAuthenticator.php`
**Terminé quand** `php bin/console lint:container` passe et que la connexion, le rafraîchissement
de jeton et la lecture des données protégées fonctionnent toujours.
**Fait** Le pare-feu `main` était un vestige : son authentificateur ne s'y déclenchait jamais,
puisqu'il ne répond qu'à `api_login`, situé sous `/api` ; et sa déconnexion pointait deux routes
inexistantes. `GET /logout` répondait 500 à chaque appel. Point d'entrée retiré, déconnexion
branchée sur `app_logout`. Aucune route protégée ne vivant dans ce pare-feu, le critère initial
« répondre 401 plutôt que rediriger » était sans objet.

### E2 · Réparer les mappings Doctrine · M
**Pourquoi** 14 associations invalides, dont des côtés propriétaires qui n'existent pas :
`ScolEnseignement#absences` pointe `EtudiantAbsence#enseignement`, `EtudiantNote#scolariteSemestre`
et `EtudiantScolariteSemestre#note` sont incohérents, `ApcReferentiel#pn` pointe un champ absent.
**Où** `uniservices/back/src/Entity/`, `uniservices/packages/*/src/Entity/`
**Terminé quand** `php bin/console doctrine:schema:validate --skip-sync` ne signale plus rien, et
que les fixtures se chargent toujours.
**Attention** à redécouper par entité si la PR dépasse deux ou trois fichiers.
**Fait** en deux PR. 18 erreurs venaient de relations renommées d'un seul côté : noms réalignés,
dont deux relations passées en unidirectionnel. Les 2 autres étaient des collections inverses
sans aucun côté propriétaire, retirées. Le schéma SQL généré est strictement identique avant et
après : 217 instructions.

### E5 · La déconnexion ne déconnecte pas · S
**Priorité haute : c'est une faille, pas une gêne.**
**Pourquoi** relevé en testant E1, antérieur à notre reprise. `POST /api/logout`, la
déconnexion qu'utilise le front, répond 500 à chaque appel : le contrôleur lit un paramètre
`JWT_COOKIE_SECURE` qui existe dans `.env` mais n'est déclaré nulle part comme paramètre du
conteneur. Le jeton de rafraîchissement est supprimé en base avant le plantage, mais les
en-têtes qui effacent les cookies sont posés après et ne partent jamais.
**Vérifié** après un clic sur « Se déconnecter », `/api/auth/me` répond toujours
« authentifié » et `/api/me/security-context` renvoie 200. Sur un poste partagé, l'étudiant
suivant récupère la session du précédent pendant toute la durée de vie du jeton.
**Où** `uniservices/back/config/services.yaml`
**Terminé quand** la déconnexion répond 200, efface les deux cookies, et que `/api/auth/me`
répond « non authentifié » juste après.
**Fait** #15 : les cookies sont effacés, la déconnexion déconnecte.

### E4 · Traiter les 271 erreurs PHPStan · L
**Pourquoi** révélées par E3, toutes antérieures à notre reprise. 271 erreurs dans 117 fichiers :
184 dans `back/`, 39 dans `intranet-bundle`, 36 dans `questionnaire-bundle`, le reste marginal.
**Commencer par les 36 qui sont probablement de vrais bugs** : 27 `method.notFound` et
9 `property.notFound`, soit des appels à des méthodes ou propriétés qui n'existent pas. Par exemple
`Etudiant::setSemestreActuel()`. Ce code plante à l'exécution dès qu'on passe dessus.
Les 235 autres sont de l'hygiène de typage : `return.type` (42), `missingType.parameter` (41),
`method.unused` (19), `missingType.return` (17), `instanceof.alwaysTrue` (17).
**Décision** pas de baseline : on corrige tout.
**Fait** en deux temps. PR #19 et #20 : les premiers appels à des membres inexistants, dont un
calendrier qui rendait cinq fois le lundi. La suite est la fiche E6.

### E6 · Corriger toutes les erreurs PHPStan · L
**Pourquoi** finir E4 : 244 erreurs restaient après le branchement Celcat.
**Fait** PR #25 à #28, de 244 à 19 erreurs. Vrais plantages corrigés en chemin : réinitialisation
du mot de passe, génération des créneaux d'EDT, liste du personnel sans statut, synthèse du
prévisionnel qui additionnait N fois le même enseignement, état d'évaluation inexistant, création
de ticket helpdesk réservée par erreur au superadmin, descriptions des filtres API ignorées.
**Fini** avec les réponses du client et de Cyndel (PR #36 et #38) : voter des questionnaires
rétabli, ECTS laissés vides dans le PDF, appel à `addAnnee()` retiré, ancien format des moyennes
supprimé. PHPStan est à 0 erreur et `phpstan.neon` ne masque plus rien.

### E7 · Faire passer CI-Cypress · M
**Pourquoi** le job échouait sur chaque PR, avant même de lancer un test.
**Fait** PR #40 : adresse de la base passée en variable d'environnement, `cypress.config.js`
ajouté, exemples de Cypress remplacés par un parcours réel (connexion du compte invité, puis
cours de l'étudiant dans l'agenda), fausse base Celcat générée en CI.
Complété par #41 (l'API lancée par `php -S` voit la base de CI) et #69 (composants PrimeVue
pré-optimisés : sans cela, Vite rechargeait la page en CI et perdait la navigation en cours).

### E8 · [back] Lecture et écriture des documents sécurisées · M
**Pourquoi** Constaté pendant B6. `security.yaml` ouvre `^/api` en `PUBLIC_ACCESS`, et
`Document` n'a aucune règle en lecture : `GET /api/documents` répond sans connexion, avec les
documents `PERSONNEL` et `DEPARTEMENT` de tous les départements. À l'inverse, `Post`, `Patch` et
`Delete` exigent `ROLE_PERSONNEL`, que personne ne reçoit (`Personnel::getRoles()` renvoie les
permissions du département) : personne ne peut créer ni modifier un document.
**Terminé quand** la lecture exige une connexion et suit les règles de visibilité de
`back/src/Service/Recherche/Source/SourceDocuments.php`, et qu'une règle d'écriture défendable
est choisie, notée dans les décisions de `04-reprise.md` et couverte par PHPUnit.

### E9 · [back] Code client au format PSR-12 · S
**Pourquoi** 170 fichiers PHP sur 451 hors PSR-12 : `make cs` ne vérifiait que les fichiers
modifiés, et chaque PR qui touchait un fichier du client mêlait reformatage et correction.
**Fait** un commit mécanique, ignoré par `git blame` (`.git-blame-ignore-revs`). `make cs`
vérifie tout l'arbre et fait partie de `make check`. Les reprises du dépôt du client passent par
`bin/upstream-diff`, qui formate les deux côtés de leur diff avant de l'appliquer.

### E10 · [back] Symfony 7.4 LTS, zéro dépréciation · M
**Pourquoi** Symfony 7.3 n'est plus maintenu depuis janvier 2026, et la 7.4 est le passage obligé
vers la 8. `debug:container --deprecations` en signale 14 : signature du voteur
`DepartmentPermissionVoter`, `TaggedIterator`, `RateLimiterFactory`, configuration de gesdinet,
`shortName` en double dans le questionnaire, `errors.xml`, `PropertyInfo\Type`.
**Terminé quand** `debug:container --deprecations` ne signale plus rien, et que PHPStan, avec
`phpstan-deprecation-rules`, trouve 0 erreur. Rector (dépendance de dev) fait les réécritures
mécaniques ; chacune est relue.
**Fait** Symfony 7.4.19. Deux défauts révélés par la montée :
- deux fournisseurs pour le planning `default` : la 7.3 ne gardait que le second en silence, la
  7.4 refuse le conteneur. Le premier, `App\Schedule`, était le squelette vide du maker ;
- la recette `routing` importerait aussi les contrôleurs des bundles, ce qui changerait le
  contrôleur de 7 routes en double (dont `api_logout`). L'import explicite est conservé.

Les `shortName` en double du questionnaire sont corrigés en un seul `#[ApiResource]` par classe.
L'option globale de déduplication aurait renommé `/api/questionnaire_questions/{uuid}` en
`/api/questionnaire_question2s/{uuid}`. Les 390 routes et le document OpenAPI sont identiques
avant et après. La connexion ne lit plus les identifiants dans la query string.

Reste une dépréciation, déclenchée par le bundle gesdinet lui-même (`Entity\AbstractRefreshToken`,
que rien ne référence chez nous) : E12 la supprime.

### E11 · [back] Doctrine DBAL 4 · M
**Pourquoi** DoctrineBundle 3, exigé par Symfony 8, ne fonctionne plus avec DBAL 3.
**Terminé quand** le SQL généré par `doctrine:schema:create --dump-sql` est identique avant et
après, et que les fixtures se chargent.
**Fait** DBAL 4.4, ORM 3.7, doctrine/collections 3, types Doctrine de Carbon 3.

Régression trouvée par CI-Cypress, qui tourne sur MySQL 8. DBAL 4 n'impose plus aux tables la
collation `utf8mb4_unicode_ci`, et MySQL 8 applique alors `utf8mb4_0900_ai_ci`, qui tient compte
des espaces finales. La fixture du PN MMI, stocké avec une espace finale, n'était plus trouvée :
l'année perdait son PN, et la recherche comme la page Documents perdaient toutes les matières.
`default_table_options` restaure la collation et le moteur de DBAL 3. Le SQL généré est de
nouveau identique, à la largeur d'affichage de `TINYINT` près, que MySQL 8 ignore. MariaDB,
utilisé en local, masquait le problème.

Sur une base créée sous DBAL 3, `schema:update` ne propose que de retirer 46 anciens
commentaires de colonne `(DC2Type:…)`, sans changer aucun type.
`app:truncate` passe à `introspectTableNames()`, et les `SET` et `TRUNCATE` bruts passent à
`executeStatement()`. Ce remplacement a été délégué à OpenCode, puis le diff a été vérifié ligne
à ligne.

### E12 · [back] Jeton de rafraîchissement : gesdinet 1.5 vers 2.x · S
**Pourquoi** la version 1.5 ne va pas au-delà de Symfony 7 ; la 2.x accepte la 7.4 et la 8. La 1.5
charge aussi une classe dépréciée, dernière dépréciation du conteneur après E10.
**Terminé quand** la connexion, le rafraîchissement et la déconnexion fonctionnent, avec le
critère de E5 pour la déconnexion.

### E13 · [back] Symfony 8.1 · M
**Pourquoi** dernière version stable. Elle n'est maintenue que jusqu'à fin janvier 2027 : il
faudra passer en 8.2, attendue en novembre 2026.
**Terminé quand** CI-Back et CI-Cypress sont vertes, et que les réponses des routes `/api/me/…`
et de la recherche sont identiques avant et après.

### E14 · [back] Notre code back en anglais · M par module
**Pourquoi** le code ajouté depuis la reprise mêle anglais et français : `MoteurRecherche`,
`CentreNotifications`, `marquerLues()`, `synchroniserCalendrier()`.
**Périmètre** nos classes, méthodes et variables PHP. Les entités du client, les URL et les
champs JSON ne changent pas : le front n'est pas touché. Une PR par module : recherche,
notifications, scolarité, Celcat, documents favoris. On y applique aussi les usages actuels
(services `final readonly`, repositories injectés, `#[CurrentUser]`, pas de requête N+1).

### E15 · [back] PHPStan au niveau max, sans baseline · L, à redécouper
**Pourquoi** le niveau 6 laisse passer le `mixed` et les nullabilités, et 4 identifiants sont
ignorés en bloc. Mesuré le 25/09 : 229 erreurs au niveau 7, 329 au 8, 1 126 au 9, 2 871 au 10,
3 481 au 10 sans les exclusions. 193 des 229 du niveau 7 sont un seul motif dans
`Command/CopyBdd`.
**Méthode** un niveau à la fois, une PR par répertoire. Le niveau de `phpstan.neon` ne monte que
quand le niveau visé est à 0 ; les exclusions sont retirées entre le 8 et le 9 ; les
`phpstan-strict-rules` en dernier. Un type PHP s'aligne sur le mapping Doctrine, jamais l'inverse,
sauf migration décidée et notée.

---

## Colonne A — Socle et accessibilité transverse

Répertoires : `uniservices/shared/components`, `uniservices/shared/styles`.
Ces fiches touchent des fichiers partagés : les faire tôt évite les conflits avec B, C et D.

### A1 · Masquer les ligatures d'icônes aux lecteurs d'écran · S
**Obsolète** corrigé : plus aucune ligature n'est lue (A11Y-1, audit 05). La suite est A10.
**Pourquoi** A11Y-1. Les libellés de navigation contiennent la ligature de l'icône, non masquée.
Un lecteur d'écran annonce « dashboard Dashboard », « group Trombinoscope », « calendar Agenda ».
**Terminé quand** toute icône décorative porte `aria-hidden="true"`, et qu'aucun nom accessible
ne contient de nom d'icône.

### A2 · Corriger les contrastes du vert et du rouge · S
**Pourquoi** A11Y-2, A11Y-3, A11Y-4. Texte blanc sur `#15C377` : 2,31:1. Sur `#F96868` : 2,91:1.
Le minimum est 4,5:1. Les blocs de cours de l'emploi du temps ont le même défaut.
**Où** thème PrimeVue et configuration Tailwind de `shared/styles`
**Terminé quand** le texte sur succès est `#0B3D26` (5,33:1) et sur danger `#4A1010` (5,25:1),
valeurs déjà mesurées et consignées dans `CLAUDE.md`. Vérifier aussi les blocs d'emploi du temps,
que l'audit n'avait pas pu mesurer.

### A3 · Lien d'évitement, un seul `header`, titres continus · S
**Audit 05** le constat reste entier : A11Y-7 (pas de lien d'évitement), A11Y-8 (repères `main`,
`nav`, `header` incohérents), A11Y-9 (toutes les pages s'appellent « Uniservices »).
**Pourquoi** A11Y-5, A11Y-6. Aucun lien d'évitement, trois `<header>` sur une même page,
hiérarchie de titres discontinue.
**Terminé quand** un lien d'évitement visible au focus ouvre la page, qu'il n'y a qu'un `banner`
par page et qu'aucun niveau de titre n'est sauté.
**Fait** #53 (LOU) : lien d'évitement, un seul `header`, titres continus. Documents exclu, reste à B2.

### A4 · Cibles tactiles de 44 px · M
**Pourquoi** MOB-4. 26 éléments interactifs sur 66 mesurent moins de 44 px dans au moins une
dimension.
**Terminé quand** aucun élément interactif ne passe sous 44 px en émulation 375 x 812.

### A5 · Charger la police Roboto · S
**Pourquoi** la direction artistique impose Roboto. Elle est déclarée dans les styles mais n'est
téléchargée nulle part : l'interface s'affiche dans la police système.
**Terminé quand** Roboto est servie par l'application, sans dépendance à un service tiers si
possible, et que le texte rendu correspond à la DA.

### A6 · Une seule couleur primaire, celle de la DA · M
**Pourquoi** DA-1, A11Y-10, A11Y-11, A11Y-13. Chaque module déclare sa propre primaire Tailwind
(jaune, violet, bleu…), posée avec du texte blanc : 1,92:1 au portail, 4,23:1 dans l'intranet.
**Terminé quand** un seul preset porte les jetons de la DA pour tous les modules, et qu'aucun
texte sur primaire ne passe sous 4,5:1.
**Fait** #64 : une seule primaire, le violet de la DA `#4D3677` (palette `VIOLET_IUT` du preset), le jaune `#F7B000` en accent (`--accent-color`) ; `primaryColor` par module supprimé. `CLAUDE.md` mis à jour par #68.

### A7 · Contrastes des textes secondaires · S
**Pourquoi** A11Y-12 : heures de l'agenda à 2,64:1, métadonnées des documents à 2,75:1, date du
jour à 4,41:1. La couleur « texte atténué » de `CLAUDE.md` passe partout.
**Fait** #67 : texte atténué de `CLAUDE.md` dans le preset (`#676D75` / `#A7ACB4`), heures de vue-cal non estompées, pastilles de groupe en noir ou blanc selon leur fond, gris de Documents foncés. Test : `contrastes-secondaires.cy.js`.

### A8 · Noms des boutons icônes, étiquettes des champs · M
**Pourquoi** A11Y-14, A11Y-15, NAV-2, EDT-3, TROMBI-3. Menu, thème, flèches de l'agenda,
chevrons des catégories sans nom ; recherche des documents, filtres du trombinoscope et case
« Se souvenir de moi » sans étiquette. La recherche de la barre haute est réglée par D3.
**Fait** #63 (LOU).

### A9 · Focus visible dans le menu, menu en `nav` · S
**Pourquoi** A11Y-16, NAV-3. Les liens du menu latéral n'ont aucun indicateur de focus.
**Fait** #57 (LOU). Le menu a ensuite été redessiné par #65 (JEREMY).

### A10 · Masquer les icônes décoratives · S
**Pourquoi** A11Y-18 : 12 à 21 PrimeIcons par page, aucune avec `aria-hidden`. Remplace A1.

### A11 · Page de connexion accessible · M
**Pourquoi** CNX-1 à CNX-4 : logo absent, carrousel en tête de tabulation avec des noms de code,
case « Se souvenir de moi » sans `id`, « Connexion URCA » à 1,92:1. S'y ajoute un défaut relevé
en écrivant les tests E2E : le bouton « Connexion invité » reste désactivé tant que le focus est
dans le mot de passe.

### A12 · Menu mobile de la barre haute fermé au chargement · S
**Pourquoi** MOB-6 : le menu d'actions est ouvert à chaque page et recouvre le contenu.

### A13 · Blocs qui débordent en mobile · S
**Pourquoi** MOB-10 : la carte « Mon dashboard » du portail sort de l'écran, d'autres blocs sont
rognés sur le tableau de bord, le trombinoscope et le profil.

### A14 · Retirer « Messages » et « Notifications », sans action · S
**Pourquoi** NAV-1 : deux entrées mortes sur chaque page. La vraie fonction reste P3.
**Fait** #58 et #60 : « Messages » retiré de la barre de l'étudiant, la cloche mène au centre de notifications (voir P3).

---

## Colonne B — Documents

Répertoire : `uniservices/packages/document-bundle`.

### B1 · Catégories en vrais liens, avec adresse propre · M
**Audit 05** DOC-13 : les catégories sont atteignables au clavier, mais toujours sans adresse,
et leurs chevrons n'ont pas de nom.
**Pourquoi** DOC-2. Les cartes de catégorie sont des `<div>` sans lien, sans `tabindex` et sans
rôle : ni atteignables au clavier, ni annoncées comme cliquables, et sans URL. Une catégorie ne
peut être ni mise en favori ni partagée.
**Terminé quand** chaque catégorie est un lien avec sa propre adresse, atteignable au clavier,
et qu'un rechargement restitue la vue.

### B2 · Réparer la hiérarchie de titres · S
**Audit 05** DOC-12 : plus de `h5` en rafale, mais plus de `h1` non plus.
**Pourquoi** DOC-5. Un `h1` suivi directement de neuf `h5`, sans `h2`.

### B3 · Afficher le compteur de documents · S
**Obsolète** corrigé : les compteurs s'affichent (DOC-3, audit 05).
**Pourquoi** DOC-3. Le libellé « Nb. de documents dans la catégorie » s'affiche sans sa valeur
sur huit cartes sur neuf. Aucune indication de volume ni de fraîcheur.
**Attention** vérifier d'abord si la valeur est absente ou seulement non affichée. C'est la
question 1 de l'audit, restée sans réponse du client.

### B4 · Recherche, filtres, tri et récents, dans l'URL · L
**Audit 05** recherche, tri et vue liste existent. Restent : la tolérance aux fautes, en passant
par `/api/recherche` (DOC-10 : « gide » ne trouve rien), les filtres dans l'URL, et l'annonce du
nombre de résultats (A11Y-17).
**Pourquoi** DOC-4. Ni recherche interne, ni filtre, ni tri, ni vue « récents ». La seule
stratégie possible est l'exploration séquentielle.
**Terminé quand** les filtres se cumulent, vivent dans l'URL, et que le nombre de résultats est
annoncé dans une région `aria-live`.
**Attention** `GET /api/documents` n'accepte **aucun filtre**, seulement la pagination. Soit on
filtre côté client sur la collection complète (`pagination=false`), soit on ajoute les filtres à
l'API — voir B6.

### B5 · Densifier la grille en mobile · S
**Audit 05** aggravé et passé **Critique** : en mobile, la liste est écrasée dans une colonne
d'environ 40 px (MOB-9). Taille revue à M.
**Pourquoi** DOC-6. À 800 px, une carte par ligne pour trois mots utiles : neuf catégories
demandent plusieurs écrans de défilement.

### B6 · [back + front] Relier document, matière et SAE · L
**Pourquoi** DOC-1, et c'est la **priorité 2** du projet. Les catégories reflètent l'organigramme
de l'établissement, pas la vie de l'étudiant. Aucune entrée « cours », « matière » ou « SAE ».
**Vérifié** l'entité `Document` n'a aucun lien vers une matière, un semestre ou une SAE. Ses
champs sont : `author`, `category`, `createdAt`, `departement`, `description`, `fileSize`,
`filename`, `id`, `isFavorite`, `mimeType`, `tags`, `titre`, `type`, `updatedAt`, `version`,
`visibility`. C'est la réponse à la question 2 de l'audit : la catégorie est le seul axe existant.
**Terminé quand** un document peut être rattaché à un `ScolEnseignement`, que l'API l'expose et
permet d'y filtrer, et que l'écran propose le classement par matière et par SAE.
**Attention** à redécouper : migration, exposition API, filtres serveur, puis interface.
**Fait** #52 : `Document.enseignement` (un seul `ScolEnseignement`, facultatif), filtres `enseignement` et `enseignement.type` sur `/api/documents`, sections « Matières » et « SAÉ » de l'écran. Au passage, l'écran chargeait seulement les 30 premiers documents.

### B7 · [back + front] Exposer les favoris · M
**Pourquoi** `isFavorite` existe dans le schéma mais n'est **pas sérialisé** dans la réponse
servie à l'étudiant. La fonctionnalité est inutilisable côté interface.
**Terminé quand** un étudiant voit ses favoris, peut en ajouter et en retirer, et que l'état
survit à un rechargement.
**Fait** #62 : favoris propres à chaque utilisateur (table `document_favori`, `/api/me/documents-favoris`). L'ancien `Document::isFavorite`, partagé par tous, est supprimé.

### B8 · Documents utilisables au clavier · M
**Pourquoi** DOC-7, **Critique** : 80 cartes et étiquettes sont des `div` non focalisables.
DOC-8 : « Télécharger » reçoit le focus en restant invisible.

### B9 · Titres de documents lisibles · S
**Pourquoi** DOC-9 : à 1 280 px, les titres sont tronqués à 4 ou 5 caractères.

### B10 · Remplacer les emoji par des icônes · S
**Pourquoi** DOC-11 : les emoji servent d'icônes et sont lus par les lecteurs d'écran.
`CLAUDE.md` les proscrit de l'interface.
**Fait** #54 : PrimeIcons à la place des emoji, test `documents.cy.js` qui échoue si un emoji revient.

### B11 · Documents en mode sombre · M
**Pourquoi** Constaté pendant A7. L'écran code en dur des fonds clairs (`bg-white`, `bg-gray-50`)
alors que le mode sombre éclaircit le texte : « Matières », « SAÉ » et « Catégories » sortent
blanc sur blanc, les descriptions de cartes à 1,93:1.
**Terminé quand** l'écran suit les jetons du thème et que tous ses textes passent 4,5:1 dans les
deux thèmes, vérifié par un test Cypress en mode sombre. Mesurer après conversion par un
`canvas` : Tailwind 4 renvoie ses couleurs en `oklch`, PrimeVue ses fonds en `color-mix`.

---

## Colonne C — Tableau de bord, emploi du temps, notes

Répertoires : `uniservices/packages/intranet-bundle`, `uniservices/packages/auth-bundle`.

### C1 · Widget « Maintenant » · M
**Pourquoi** TB-1, TB-2. La grille hebdomadaire complète occupe le premier écran alors que
l'information la plus demandée est « mon prochain cours, à quelle heure, dans quelle salle ». Ni
le cours en cours ni le jour courant ne sont mis en évidence.
**Terminé quand** le haut du tableau de bord annonce le prochain cours, sa salle et le temps
restant, et dit explicitement qu'il n'y en a plus quand la journée est finie.
**Fait** #23, salle corrigée par #44 (JEREMY).

### C2 · Ne plus rendre les tableaux vides · S
**Obsolète** sans objet : il n'y a plus de tableaux de notes (TB-3, audit 05).
**Pourquoi** TB-3. Les tableaux vides sont rendus intégralement, en-têtes compris, avec une
ligne « Aucune note n'a été saisie ». Deux blocs occupent l'écran pour ne rien dire.

### C3 · Emploi du temps mobile · L
**Audit 05** MOB-7 : la vue semaine s'ouvre par défaut et reste illisible sur téléphone. Aussi
MOB-8 (« Aujourd'hui » coupé), EDT-4 (deux numérotations de semaine), EDT-5 (sous-titre qui
promet des onglets absents).
**Pourquoi** MOB-1, MOB-2, MOB-3, MOB-5, et c'est la **priorité 4**. En vue jour, le bloc de
cours est dans une colonne décalée, sans axe horaire ni en-tête de jour, avec une large zone vide
à sa gauche. Deux barres de navigation temporelle empilées aux styles incohérents. Le libellé
« Déposer un justificatif » déborde. L'avertissement Celcat occupe quatre lignes avant tout
contenu utile.

### C8 · [back] Connecteur Celcat · L
**Kanban** C0.
**Pourquoi** l'emploi du temps officiel vit dans Celcat, et uniServices ne savait que le recopier
depuis l'intranet actuel. Sans source, l'emploi du temps mobile (C3) n'aurait rien à afficher.
**Fait** Commande `app:celcat:sync`, qui reprend les requêtes et les règles de l'intranet V3 :
dépliage des semaines, clé cours-semaine-jour-groupe, mise à jour sans recréation, créneau
conservé s'il porte des absences. Développée contre une fausse base Celcat SQLite de mêmes
tables. Reste à la brancher sur la vraie base : il faut l'accès réseau et les identifiants de
la DSI, que seul le client peut obtenir.

### C9 · [back + front] Réparer l'emploi du temps étudiant · M
**Pourquoi** l'écran Agenda d'un étudiant affichait une erreur à chaque chargement.
**Fait** PR #24, #29 et #30. Trois causes cumulées : un appel à `/apitrue/etudiant_scolarites`
(404) ; les créneaux Celcat, sans intervenant ni matière, lus sans précaution ; et le filtre
`groupe` de l'API, qui produisait un SQL invalide dès deux groupes, donc toujours pour un
étudiant. Les groupes sont désormais lus sur les semestres de l'année affichée. Côté fixtures,
l'étudiant de test est inscrit en S1 dans `MMICM`, `MMITDAB` et `MMITPA`, et les années
universitaires sont calculées à partir de la date du jour, comme la fausse base Celcat.
**Reste** l'intervenant et la matière des créneaux Celcat restent inconnus : les codes de la
fausse base ne correspondent pas à ceux des fixtures. La passe visuelle est la fiche C3.

### C4 · Distinguer « pas encore notée », « absent » et « zéro » · M
**Obsolète** pour l'instant : aucune note n'est affichée. Repris dans F1.
**Pourquoi** relevé dans l'audit informel : des 0 s'affichent en cours d'année comme si
l'étudiant avait eu 0. C'est un défaut qui fausse la lecture de ses résultats.
**Vérifié** `EtudiantNote` porte `note` (qui peut être nulle), `publiee` et `presenceStatut`.
L'information nécessaire existe, elle n'est pas exploitée à l'affichage.
**Terminé quand** une note non publiée, une absence et un zéro réel sont visuellement distincts,
et que la moyenne n'intègre pas les deux premiers cas.

### C5 · Retirer le bouton « Configurer » du portail étudiant · S
**Obsolète** prémisse fausse : `/api/widgets/available/intranet` répond maintenant 200 à un
étudiant (audit 05).
**Pourquoi** le portail propose « Personnalisez vos widgets », mais l'API répond **403** à un
étudiant, aussi bien sur `/api/widgets/available/intranet` que sur le `PATCH` de mise en page.
Le bouton échoue en silence.
**Terminé quand** le bouton n'apparaît que pour les profils qui y ont droit. Décider avec le
client si la personnalisation doit être ouverte aux étudiants, ou retirée pour eux.

### C6 · Modalités de contrôle des connaissances · M
**Audit 05** les modalités ne sont plus affichées nulle part (TB-4). Fiche en cours chez JEREMY :
à confirmer avec lui avant de la retirer.
**Pourquoi** TB-4, confirmé par l'audit informel. Le tableau affiche des compétences suivies de
nombres entre parenthèses, sans légende. L'information n'est pas interprétable.
**Terminé quand** soit une légende rend le tableau lisible, soit le bloc est retiré du tableau de
bord — à trancher avec le client.

### C7 · Trois nettoyages du tableau de bord · S
**Obsolète** TB-6 et TB-7 sont corrigés, TB-5 devient POR-2 : remplacée par C12.
**Pourquoi** TB-5, le bloc Contacts répète six fois les deux mêmes personnes, une fois par
parcours du BUT. TB-6, l'avertissement Celcat s'affiche à chaque visite sans pouvoir être masqué.
TB-7, un bouton flottant orange sans libellé se superpose au contenu.

---

### C10 · [back + front] Widget « Aujourd'hui » juste · S
**Pourquoi** TB-8, **Critique** : le widget annonce « Aucun événement aujourd'hui » alors qu'un
cours a lieu. `edt_events?day=` renvoie 0 créneau quand `semaineFormation=` renvoie bien le cours.
TB-9 : il contredit le widget « Maintenant ».
**Fait** #61 : le fournisseur renvoyait une liste vide à tout étudiant ; il lit les cours du jour de ses groupes, avec la règle du widget « Maintenant ». Bouton « Faire l'appel » masqué. Test : `widget-aujourdhui.cy.js`.

### C11 · Widget « Notes » : notes de l'étudiant, ou retiré · S
**Pourquoi** TB-10 : le widget montre des pense-bêtes destinés au personnel, en barres grises.
**Fait** #51 (JEREMY).

### C12 · Nettoyer le portail · S
**Pourquoi** POR-2 : contacts « JOHN DOE » écrits en dur, liens utiles sans destination. POR-3 :
le bouton « Retour » du portail renvoie à la connexion. Remplace C7.
**Fait** #56 (JEREMY), puis #70 : l'étudiant ne passe plus par le portail depuis #58.

### C13 · Portail : applications non activées · S
**Pourquoi** POR-1 : six applications grisées, sous leur nom de code, occupent le premier écran
en mobile.
**Obsolète** depuis #58 : l'étudiant ne voit plus le portail ; la liste « Non activé » ne concerne plus que le personnel (#70).

### C14 · Agenda utilisable au clavier · M
**Pourquoi** EDT-1 : les cours ne sont pas atteignables au clavier. EDT-3 : flèches sans nom. Le
changement de semaine n'est pas annoncé (A11Y-17).
**Fait** #71 (JEREMY).

### C15 · Retirer les actions enseignant de la vue étudiante · S
**Pourquoi** EDT-2 : « Appel » et « Tous présents » sont proposés à l'étudiant, sans effet.
**Fait** #75 (JEREMY).

### C16 · [back] Intervenants de l'agenda présents dans l'annuaire · S
**Pourquoi** EDT-6 : les intervenants de la fausse base Celcat n'existent pas dans les fixtures,
donc la recherche ne les trouve pas. C'est la réserve de C9.

---

## Colonne D — Recherche

**Priorité 1 du projet**, et la colonne la plus lourde. RECH-1 est, selon l'audit, le défaut le
plus pénalisant à l'usage.

**Vérifié avant de découper : l'API n'expose aujourd'hui aucune route de recherche.** Cette
colonne commence donc par du back, pas par de l'interface.

### D1 · [back] Endpoint de recherche tolérant aux fautes · L
**Fait** PR #34. MariaDB et PHP, sans dépendance ni serveur supplémentaire. `GET /api/recherche?q=`
limité au département de l'utilisateur, comme la V3. Accents, casse, lettre oubliée, en trop,
remplacée ou inversée, mot inachevé. Environ 60 ms, 36 à 55 ms de calcul pour 3 000 fiches.
**Pourquoi** RECH-1. « annebicque » renvoie deux résultats, « anebicque » zéro, sans suggestion.
**Terminé quand** un endpoint renvoie des résultats pertinents malgré une faute de frappe, sur
plusieurs types, en restant sous la centaine de millisecondes — l'existant répond en 50 à 90 ms,
c'est le niveau à tenir.
**Attention** à cadrer avant de coder : tolérance par distance d'édition en SQL, ou index dédié.
Le choix engage l'infrastructure, il se discute en équipe et avec le client.

### D2 · [back] Élargir le périmètre indexé · M
**Fait** PR #35 : matières et SAÉ (par code ou libellé), actualités du département. L'emploi du
temps passe par les matières plutôt que par chaque créneau. Les pages de l'intranet reviennent à
la palette front (D3).
**Pourquoi** RECH-2. Seuls trois types sont cherchables : Étudiants, Permanents, Documents. Les
matières, l'emploi du temps, les actualités et les pages ne le sont pas. « developpement front »
ne renvoie rien alors que la matière existe.

### D3 · [front] Palette de recherche accessible · L
**Fait** PR #43. Corrige RECH-10 et RECH-12 de l'audit 05.
**Pourquoi** RECH-3, RECH-4, RECH-5. Le champ n'a ni `role="combobox"`, ni `aria-expanded`, ni
`aria-controls`. Aucune région `aria-live` : l'arrivée des résultats n'est pas annoncée. La
surcouche n'a pas de `role="dialog"` et Échap ne la ferme pas. Aucune navigation des résultats au
clavier.
**Terminé quand** la palette s'ouvre au clavier, se parcourt aux flèches, se valide à Entrée, se
ferme à Échap, et annonce le nombre de résultats.

### D4 · [front] Lisibilité des résultats · M
**Fait** PR #46 : champ élargi, statut, adresse et action des
personnes, message vide unique, contrastes et focus mesurés en clair et en sombre.
**Pourquoi** RECH-6, le champ tronque la requête au-delà d'une vingtaine de caractères. RECH-7,
les résultats « personne » n'affichent que nom et adresse, sans rôle, sans département et sans
action. RECH-8, trois blocs « pas de résultat » s'affichent simultanément.

### D5 · Signaler les comptes de test en production · S
**Obsolète** sans objet dans uniServices : ce sont nos fixtures (RECH-9, audit 05).
**Pourquoi** RECH-9. Des comptes de test apparaissent dans les résultats de production.
**Terminé quand** le client a été prévenu. Ce n'est pas à nous de nettoyer sa base : la fiche se
ferme sur un message, pas sur un commit.

### D6 · Recherche en mobile · S
**Pourquoi** RECH-11 : sous 1 024 px, les deux champs de recherche sont masqués.

---

## Colonne F — Scolarité, profil, trombinoscope

Répertoire : `uniservices/packages/intranet-bundle`, vues de l'étudiant. Colonne ouverte par
l'audit 05 : ces écrans existent mais sont vides ou cassés.

### F1 · [back + front] Page Scolarité : notes, absences, moyennes · L
**Pourquoi** SCO-1, **Critique** : la page est un gabarit « Semestre : OK », l'étudiant n'a accès à
aucune note, absence ni moyenne. Reprend C4.
**Fait** #49 (`GET /api/me/scolarite`, moyennes calculées à la volée) et #50 (page). Test : `scolarite.cy.js`.

### F2 · Cahier de texte · M
**Pourquoi** SCO-2 : même gabarit inachevé.

### F3 · Profil étudiant rempli · S
**Pourquoi** SCO-3 : prénom, nom, login et adresse restent vides alors que l'API les renvoie.

### F4 · Trombinoscope : plus de JSON brut · S
**Pourquoi** TROMBI-1, **Critique** : chaque fiche affiche un objet JSON de l'API.

### F5 · Trombinoscope : accès étudiant et export · S
**Pourquoi** TROMBI-2 : un bouton « Exporter les données » est proposé à un étudiant.

---

## Propositions d'amélioration

Au-delà des corrections. Chacune répond à un constat, aucune n'est une idée en l'air.

### P1 · Fiche matière · L
L'audit pose le constat de fond : **rien ne porte la notion de matière ou de SAE**, alors que
c'est l'unité dans laquelle l'étudiant organise toute sa scolarité. Les documents, les notes et
l'emploi du temps vivent dans trois silos qui ne se rejoignent jamais.

Une page par matière, agrégeant ses créneaux à venir, ses documents, ses notes et ses absences.
C'est la réponse la plus directe au besoin exprimé par le client, et elle rend visibles d'un coup
les priorités 2 et 3. Dépend de B6.

### P2 · Déposer un justificatif d'absence · M
**Vérifié** un étudiant **ne peut pas** déposer son justificatif : la route `POST` n'existe que
sous `/api/administration/etudiant_absence_justificatifs`. L'audit ne relevait que le débordement
du libellé du bouton en mobile ; le problème est plus profond que la mise en page.
Back et front.

### P3 · Notifications distinctes de la messagerie · M
Demandé dans l'audit informel. **Aucune API de notification ni de messagerie n'existe** : seul le
helpdesk expose des messages. À cadrer avec le client avant toute estimation.
**Fait autrement** #59 et #60, sur décision de LCS : notifications et messages **réunis** dans un seul centre (cloche de la barre haute, page `/intranet/notifications`). Les messages sont les copies des e-mails que l'intranet envoie à l'étudiant, captées à l'envoi ; la boîte universitaire n'est pas lue.

### P4 · Page contact explicative et liens utiles redessinés · M
Demandés dans l'audit informel. `/api/lien_utiles` existe déjà et expose les opérations
nécessaires, le travail est surtout d'interface.

### P5 · Vocabulaire de la navigation · S
**Audit 05** c'est pire : le portail et la connexion affichent les noms de code des modules
(« DocumentBundle », « StageBundle »).
L'audit relève que la navigation nomme des objets administratifs — « Trombinoscope »,
« Applications », « Modalités de Contrôle des Connaissances » — quand l'étudiant cherche un cours,
une salle, une note, une personne. Renommer coûte peu et se remarque tout de suite.
**Fait en partie** #58 : menu étudiant « Accueil », « Emploi du temps », « Notes et absences ». Reste le fil d'Ariane, qui dit encore « Dashboard » sur plusieurs pages.

---

## Limites de cette revue

À dire au client plutôt qu'à cacher.

Nous n'avons accès qu'à la **vue étudiante**. Certains choix critiqués ici existent peut-être
pour satisfaire la vue enseignant, que nous ne voyons pas : un bloc inutile pour un étudiant peut
être indispensable à un responsable de formation. Tant que nous n'avons pas de compte enseignant,
nos propositions sur les écrans partagés restent des hypothèses.

L'audit lui-même laisse quatre écrans non couverts : Agenda, Applications, Trombinoscope et
Messagerie. Aucun test utilisateur n'a encore été mené, aucun audit RGAA formel au lecteur d'écran
non plus. Les constats sont solides sur le code et les mesures, plus fragiles sur les usages.

---

## Décisions à trancher en équipe

1. **Mode sombre.** L'audit informel demande de le supprimer. L'audit structuré dit qu'il existe
   déjà chez le client et qu'il faut le prévoir dès la conception du design system. Les deux
   documents se contredisent, il faut choisir avant d'attaquer la colonne A.
2. **Emoji.** `CLAUDE.md` les interdit partout. Le code du client en contient, notamment dans les
   icônes de catégories de documents. Préciser si la règle vaut pour le code que nous écrivons ou
   pour tout le dépôt.
3. **Personnalisation du tableau de bord.** Ouverte aux étudiants, ou réservée au personnel ?
   L'API tranche aujourd'hui par un 403, l'interface prétend le contraire.

## Questions au client toujours sans réponse

1. Les compteurs de documents sont-ils vides faute de documents, ou est-ce un défaut d'affichage ?
   Bloque B3.
2. Peut-on ajouter une relation entre un document et une matière ou une SAE ? Bloque B6, donc la
   priorité 2 et la fiche P1.
3. Trois vocabulaires désignent la même application : `packages` renvoie `documents`, le catalogue
   de widgets `document`, la fiche étudiant `UniTranet`. Lequel fait foi ?
