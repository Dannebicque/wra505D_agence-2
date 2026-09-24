# Backlog — espace étudiant

Une fiche par tâche, à recopier dans le kanban. Chaque fiche tient dans une branche et une PR,
comme l'exige `CLAUDE.md`.

## Comment s'en servir

Les tâches sont réparties en quatre colonnes qui suivent les **répertoires**, pas les
fonctionnalités. C'est ce qui permet à trois personnes de travailler en parallèle sans conflit :
prenez une colonne chacun plutôt qu'une fiche au hasard.

Chaque fiche indique les constats d'audit qu'elle couvre. Les identifiants `TB`, `DOC`, `RECH`,
`MOB` et `A11Y` renvoient à `01-audit-existant.md`. Les fiches marquées **[back]** touchent le
Symfony, celles marquées **[back + front]** les deux.

Tailles : **S** une session, **M** une journée, **L** plusieurs jours, à redécouper avant de
commencer.

---

## État de la CI

Les workflows du client sont maintenant à la racine et s'exécutent sur chaque PR vers `develop`.
Trois échouent dès le premier passage, sur des défauts **antérieurs à notre reprise** : ils
n'avaient jamais tourné, les fichiers étant rangés dans `uniservices/.github/`, répertoire que
GitHub ne lit pas.

| Contrôle | État | Fiche |
|---|---|---|
| `make check-front` | passe | — |
| `composer validate` | passe, deux avertissements | — |
| `lint:container` | passe | — |
| `doctrine:schema:validate` | passe | — |
| `phpstan` | 0 erreur, aucune masquée | E6 |
| Cypress | un parcours E2E ; la CI attend `fix/ci-cypress-serveur` | E7 |

E1 à E6 sont faites, CI-Back et CI-Packages sont au vert. E7 finit CI-Cypress.

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
**Reste** `fix/ci-cypress-serveur` : l'API lancée par `php -S` ne voyait pas cette variable.

---

## Colonne A — Socle et accessibilité transverse

Répertoires : `uniservices/shared/components`, `uniservices/shared/styles`.
Ces fiches touchent des fichiers partagés : les faire tôt évite les conflits avec B, C et D.

### A1 · Masquer les ligatures d'icônes aux lecteurs d'écran · S
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
**Pourquoi** A11Y-5, A11Y-6. Aucun lien d'évitement, trois `<header>` sur une même page,
hiérarchie de titres discontinue.
**Terminé quand** un lien d'évitement visible au focus ouvre la page, qu'il n'y a qu'un `banner`
par page et qu'aucun niveau de titre n'est sauté.

### A4 · Cibles tactiles de 44 px · M
**Pourquoi** MOB-4. 26 éléments interactifs sur 66 mesurent moins de 44 px dans au moins une
dimension.
**Terminé quand** aucun élément interactif ne passe sous 44 px en émulation 375 x 812.

### A5 · Charger la police Roboto · S
**Pourquoi** la direction artistique impose Roboto. Elle est déclarée dans les styles mais n'est
téléchargée nulle part : l'interface s'affiche dans la police système.
**Terminé quand** Roboto est servie par l'application, sans dépendance à un service tiers si
possible, et que le texte rendu correspond à la DA.

---

## Colonne B — Documents

Répertoire : `uniservices/packages/document-bundle`.

### B1 · Catégories en vrais liens, avec adresse propre · M
**Pourquoi** DOC-2. Les cartes de catégorie sont des `<div>` sans lien, sans `tabindex` et sans
rôle : ni atteignables au clavier, ni annoncées comme cliquables, et sans URL. Une catégorie ne
peut être ni mise en favori ni partagée.
**Terminé quand** chaque catégorie est un lien avec sa propre adresse, atteignable au clavier,
et qu'un rechargement restitue la vue.

### B2 · Réparer la hiérarchie de titres · S
**Pourquoi** DOC-5. Un `h1` suivi directement de neuf `h5`, sans `h2`.

### B3 · Afficher le compteur de documents · S
**Pourquoi** DOC-3. Le libellé « Nb. de documents dans la catégorie » s'affiche sans sa valeur
sur huit cartes sur neuf. Aucune indication de volume ni de fraîcheur.
**Attention** vérifier d'abord si la valeur est absente ou seulement non affichée. C'est la
question 1 de l'audit, restée sans réponse du client.

### B4 · Recherche, filtres, tri et récents, dans l'URL · L
**Pourquoi** DOC-4. Ni recherche interne, ni filtre, ni tri, ni vue « récents ». La seule
stratégie possible est l'exploration séquentielle.
**Terminé quand** les filtres se cumulent, vivent dans l'URL, et que le nombre de résultats est
annoncé dans une région `aria-live`.
**Attention** `GET /api/documents` n'accepte **aucun filtre**, seulement la pagination. Soit on
filtre côté client sur la collection complète (`pagination=false`), soit on ajoute les filtres à
l'API — voir B6.

### B5 · Densifier la grille en mobile · S
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

### B7 · [back + front] Exposer les favoris · M
**Pourquoi** `isFavorite` existe dans le schéma mais n'est **pas sérialisé** dans la réponse
servie à l'étudiant. La fonctionnalité est inutilisable côté interface.
**Terminé quand** un étudiant voit ses favoris, peut en ajouter et en retirer, et que l'état
survit à un rechargement.

---

## Colonne C — Tableau de bord, emploi du temps, notes

Répertoires : `uniservices/packages/intranet-bundle`, `uniservices/packages/auth-bundle`.

### C1 · Widget « Maintenant » · M
**Pourquoi** TB-1, TB-2. La grille hebdomadaire complète occupe le premier écran alors que
l'information la plus demandée est « mon prochain cours, à quelle heure, dans quelle salle ». Ni
le cours en cours ni le jour courant ne sont mis en évidence.
**Terminé quand** le haut du tableau de bord annonce le prochain cours, sa salle et le temps
restant, et dit explicitement qu'il n'y en a plus quand la journée est finie.

### C2 · Ne plus rendre les tableaux vides · S
**Pourquoi** TB-3. Les tableaux vides sont rendus intégralement, en-têtes compris, avec une
ligne « Aucune note n'a été saisie ». Deux blocs occupent l'écran pour ne rien dire.

### C3 · Emploi du temps mobile · L
**Pourquoi** MOB-1, MOB-2, MOB-3, MOB-5, et c'est la **priorité 4**. En vue jour, le bloc de
cours est dans une colonne décalée, sans axe horaire ni en-tête de jour, avec une large zone vide
à sa gauche. Deux barres de navigation temporelle empilées aux styles incohérents. Le libellé
« Déposer un justificatif » déborde. L'avertissement Celcat occupe quatre lignes avant tout
contenu utile.

### C8 · [back] Connecteur Celcat · L
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
**Pourquoi** relevé dans l'audit informel : des 0 s'affichent en cours d'année comme si
l'étudiant avait eu 0. C'est un défaut qui fausse la lecture de ses résultats.
**Vérifié** `EtudiantNote` porte `note` (qui peut être nulle), `publiee` et `presenceStatut`.
L'information nécessaire existe, elle n'est pas exploitée à l'affichage.
**Terminé quand** une note non publiée, une absence et un zéro réel sont visuellement distincts,
et que la moyenne n'intègre pas les deux premiers cas.

### C5 · Retirer le bouton « Configurer » du portail étudiant · S
**Pourquoi** le portail propose « Personnalisez vos widgets », mais l'API répond **403** à un
étudiant, aussi bien sur `/api/widgets/available/intranet` que sur le `PATCH` de mise en page.
Le bouton échoue en silence.
**Terminé quand** le bouton n'apparaît que pour les profils qui y ont droit. Décider avec le
client si la personnalisation doit être ouverte aux étudiants, ou retirée pour eux.

### C6 · Modalités de contrôle des connaissances · M
**Pourquoi** TB-4, confirmé par l'audit informel. Le tableau affiche des compétences suivies de
nombres entre parenthèses, sans légende. L'information n'est pas interprétable.
**Terminé quand** soit une légende rend le tableau lisible, soit le bloc est retiré du tableau de
bord — à trancher avec le client.

### C7 · Trois nettoyages du tableau de bord · S
**Pourquoi** TB-5, le bloc Contacts répète six fois les deux mêmes personnes, une fois par
parcours du BUT. TB-6, l'avertissement Celcat s'affiche à chaque visite sans pouvoir être masqué.
TB-7, un bouton flottant orange sans libellé se superpose au contenu.

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
**Pourquoi** RECH-3, RECH-4, RECH-5. Le champ n'a ni `role="combobox"`, ni `aria-expanded`, ni
`aria-controls`. Aucune région `aria-live` : l'arrivée des résultats n'est pas annoncée. La
surcouche n'a pas de `role="dialog"` et Échap ne la ferme pas. Aucune navigation des résultats au
clavier.
**Terminé quand** la palette s'ouvre au clavier, se parcourt aux flèches, se valide à Entrée, se
ferme à Échap, et annonce le nombre de résultats.

### D4 · [front] Lisibilité des résultats · M
**Pourquoi** RECH-6, le champ tronque la requête au-delà d'une vingtaine de caractères. RECH-7,
les résultats « personne » n'affichent que nom et adresse, sans rôle, sans département et sans
action. RECH-8, trois blocs « pas de résultat » s'affichent simultanément.

### D5 · Signaler les comptes de test en production · S
**Pourquoi** RECH-9. Des comptes de test apparaissent dans les résultats de production.
**Terminé quand** le client a été prévenu. Ce n'est pas à nous de nettoyer sa base : la fiche se
ferme sur un message, pas sur un commit.

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

### P4 · Page contact explicative et liens utiles redessinés · M
Demandés dans l'audit informel. `/api/lien_utiles` existe déjà et expose les opérations
nécessaires, le travail est surtout d'interface.

### P5 · Vocabulaire de la navigation · S
L'audit relève que la navigation nomme des objets administratifs — « Trombinoscope »,
« Applications », « Modalités de Contrôle des Connaissances » — quand l'étudiant cherche un cours,
une salle, une note, une personne. Renommer coûte peu et se remarque tout de suite.

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
