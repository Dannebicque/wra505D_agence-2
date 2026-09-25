# Reprise du travail

État du projet et suite à donner, pour qu'une nouvelle session reparte sans rien redécouvrir.
À lire après `CLAUDE.md`. À mettre à jour à chaque étape importante.

Dernière mise à jour : 24/09/2026.

---

## En deux minutes

- **Volet 1**, le site de l'agence : Next.js, en ligne sur <https://figmium.vercel.app>. Hors de
  ce dépôt. C'est lui qui porte la contrainte Jamstack du sujet.
- **Volet 2**, l'espace étudiant : on reprend la base du client, uniServices, importée dans
  `uniservices/`. Front Vue 3 + Vite + PrimeVue, back Symfony 7 + API Platform, MariaDB.
- Le détail des tâches est dans `docs/03-backlog.md`, les constats d'audit dans
  `docs/01-audit-existant.md`, l'audit informel de l'équipe dans le `.docx` du dossier Drive.

## Méthode de travail

- Tout part de `develop`. **`main` n'est jamais touchée.**
- Une tâche, une branche, une PR vers `develop`. Commits en anglais, Conventional Commits.
- **Claude ouvre les PR, l'utilisateur les merge.** `gh` est installé et connecté au compte de
  LCS depuis le 25/09/2026 : Claude pousse la branche, ouvre la PR et lit la CI
  (`gh run view <id> --log-failed`). Le ruleset du dépôt exige une approbation : le merge reste
  à l'utilisateur.
- Après un merge annoncé, vérifier qu'il a bien eu lieu, puis supprimer la branche des deux
  côtés :
  `git merge-base --is-ancestor origin/<branche> origin/develop`.
- Corriger un bug dans le code back du client est permis, même hors du périmètre étudiant. On n'y
  ajoute pas de fonctionnalité.
- **Style PHP : PSR-12 sur les seuls fichiers modifiés.** Depuis `uniservices/back` : `make cs`
  vérifie, `make cs-fix` corrige, par rapport à `origin/develop` (`BASE=...` pour une autre base).
  La CI-Back le vérifie sur chaque PR. On ne reformate pas tout le code du client : ses diffs ne
  s'appliqueraient plus lors des reprises. Un fichier du client que l'on touche passe, lui, à la
  norme en entier.

## Lancer le projet en local

La procédure complète est dans le `README.md`. En résumé, depuis `uniservices/` :

| Service | Commande | Adresse |
|---|---|---|
| Base et mail | `docker compose -f docker/docker-compose.yml --env-file .env.local up -d db maildev` | MariaDB sur 3307, maildev sur 1080 |
| API | `cd back && symfony server:start -d --port=8000` | <http://127.0.0.1:8000/api> |
| Front | `pnpm run dev` | <http://localhost:3000/app/> |

Comptes de fixtures, mot de passe `test` : `etudiant`, `personnel`, `superadmin`. À saisir dans
le bloc « Compte invité ».

Emploi du temps simulé, depuis `uniservices/back` :

```bash
php bin/console app:celcat:fausse-base && php bin/console app:celcat:sync
```

avec `CELCAT_DSN="sqlite:%kernel.project_dir%/var/celcat/fausse-base.sqlite"` dans
`back/.env.local`.

---

## Ce qui est fait

| PR | Contenu |
|---|---|
| #2 | règles du projet et documentation |
| #3, #5 à #10 | un front Nuxt, **retiré ensuite** (PR #12) |
| #4 | import de la base uniServices dans `uniservices/` |
| #11 | correction de la stack dans `CLAUDE.md` et `PROJET.md` |
| #13 | CI : les workflows du client déplacés à la racine, où GitHub les exécute enfin |
| #14 | PHPStan analysait un bundle supprimé : il tourne maintenant jusqu'au bout |
| #15 | **faille** : la déconnexion ne déconnectait pas, les cookies n'étaient jamais effacés |
| #16 | pare-feu `main` invalide : `GET /logout` répondait 500 |
| #17, #18 | 20 erreurs de mappings Doctrine corrigées, schéma SQL strictement identique |
| #19, #20 | premiers bugs relevés par PHPStan, dont un calendrier qui rendait cinq fois le lundi |
| #21 | ce document |
| #22 | connecteur Celcat, développé contre une fausse base, et PHPUnit ajouté au back (26 tests) |
| #23 | widget « Maintenant » du tableau de bord (fiche C1) |
| #24 | l'emploi du temps étudiant plantait à chaque chargement (fiche C9) |
| #25 à #28 | PHPStan de 244 à 19 erreurs, avec les vrais plantages trouvés en chemin (fiche E6) |
| #27 | trois bugs en attente du client masqués dans `phpstan.neon`, un par un |
| #29 | étudiant de test inscrit dans ses groupes ; un bug des fixtures lui retirait son semestre |
| #30 | années universitaires des fixtures calculées à partir de la date du jour |
| #31 | accessibilité transverse, côté LOU |
| #33 | retour à l'année active quand l'année mémorisée n'existe plus |
| #34, #35 | recherche tolérante aux fautes, fiches D1 et D2 : `GET /api/recherche?q=` |
| #36 | réponses du client : ECTS du PDF de stage, `addAnnee()`, voter des questionnaires |
| #37 | veille du dépôt amont, voir « Dépôt du client » plus bas |
| #38 | ancien format des moyennes par UE retiré : PHPStan à 0 erreur, sans rien masquer |
| #39 | reprise du `main` amont jusqu'à `ef38ca880` (release 0.1.11) |
| #40 | CI-Cypress : base de données, configuration, premier vrai parcours E2E |
| #44 | widget « Maintenant » : la salle s'affiche telle que Celcat la donne (plus de « Salle Salle B204 ») |
| #47 | widget « Contacts » du tableau de bord : un département par ligne, celui de l'étudiant en premier |
| #51 | widget « Notes » : les cinq dernières notes publiées de l'étudiant, au lieu des pense-bêtes du personnel (fiche C11) |
| #56 | portail nettoyé (fiche C12), une seule barre de défilement par page, widget « Contacts » en cartes |

Le connecteur Celcat, en bref :

- `CelcatReader` exécute les requêtes de l'intranet V3 via PDO. Seule l'adresse de la base
  change entre développement (SQLite) et production (SQL Server).
- `CelcatEventConverter` déplie le masque de semaines `YYNY…` en créneaux datés.
- `CelcatSynchronizer` met à jour sans recréer, et **conserve un créneau qui porte des absences**
  (la base refuse de le supprimer : `ON DELETE RESTRICT`). Il remplit aussi `StructureCalendrier`,
  sans quoi l'écran étudiant ne sait pas quelle semaine afficher.
- La fausse base place la rentrée sur le septembre en cours, et la synchronisation écrit dans
  l'année universitaire active.

### État de la CI

| Job | État | Pourquoi |
|---|---|---|
| CI-Packages (front) | vert | |
| CI-Back | vert | PHPStan à 0 erreur depuis #38 |
| CI-Cypress | rouge | toutes les étapes passent sauf le test : l'API lancée par `php -S` ignorait la base de CI. Corrigé par `fix/ci-cypress-serveur`, à merger |

La recherche (D1, D2) cherche étudiants, personnels, documents, matières et actualités du
département de l'utilisateur, en respectant la visibilité des documents et le public des
actualités. Les pages de l'intranet seront cherchées par la palette front (D3), qui connaît la
navigation. Le code est dans `back/src/Service/Recherche/` : un nouveau type se branche en
ajoutant une source.

### Dépôt du client

Le `main` de [IUTTroyes/uniServices](https://github.com/IUTTroyes/uniServices) évolue encore
(Cyndel, David Annebicque). Règle : **on ne reprend un commit amont que s'il est bon et ne casse
rien**, leur base ayant beaucoup de défauts. Seul leur `main` compte, pas leurs branches.

- Dernier commit examiné : noté dans `.github/uniservices-amont-examine`, aujourd'hui `ef38ca880`.
- Le workflow « Veille uniServices » ouvre une issue chaque matin de semaine s'il y a du nouveau.
  Il ne tourne que depuis la branche par défaut, `main` : pas avant le prochain passage vers `main`.
- Procédure : dans le clone `Reference/uniServices`, `git fetch` puis `git diff`, appliquer ce qui
  est retenu avec `git apply --directory=uniservices -3` sur une branche `chore/amont-<version>`,
  puis PHPStan et PHPUnit.

---

## Répartition dans l'équipe

Suivie dans le tableau de tâches de l'équipe, hors de ce dépôt.

| Qui | Colonne du backlog |
|---|---|
| LCS | E, back et CI |
| LOU | A, accessibilité transverse |
| JEREMY | C, tableau de bord et emploi du temps |

La colonne B (documents) n'a encore personne, alors que c'est la priorité 2 du client. D1 et D2
sont faites, D3 et D4 restent à prendre.

---

## Ce qu'il reste à faire, dans l'ordre

1. **Merger `fix/ci-cypress-serveur`**, puis vérifier que CI-Cypress passe au vert sur GitHub.
2. **D3, la palette de recherche accessible**, branchée sur `/api/recherche`, avec les pages de
   l'intranet cherchées côté front.
3. **B6, relier un document à une matière ou à une SAE** : la question 6 reste sans réponse, on
   tranche nous-mêmes (voir « Décisions »).
4. **Passe visuelle de l'emploi du temps**, fiche C3 et priorité 4, côté JEREMY.
5. **Examiner les 18 alertes de sécurité** remontées par `composer audit`, antérieures à nous.
6. **Import d'étudiants** : il inscrit chaque étudiant dans *tous* les groupes du semestre, TD et
   TP compris, au lieu des siens.
7. Le reste du backlog, colonnes A à D et propositions P.

## Décisions

Quand le client n'a pas tranché, **on choisit ce qui nous paraît le mieux**, on le note ici, et
on corrige s'il décide autrement. On ne masque pas une erreur en attendant.

| Sujet | Décision | Source |
|---|---|---|
| ECTS du stage | la balise `{stage.ects}` sort vide dans le PDF, en attendant leur décision | client |
| Création d'un étudiant | inscrit d'office aux deux semestres de son année ; la notion de semestre en cours disparaît au profit de la clôture d'un semestre | Cyndel |
| Moyennes | calculées à la volée jusqu'à validation en sous-commission, puis enregistrées dans `MoyenneUe` et `MoyenneEnseignement`, liées à `EtudiantScolariteSemestre` ; celles de l'année restent calculées à la volée. L'ancien format JSON est retiré chez nous (#38), Cyndel le retire aussi chez eux | Cyndel |
| Voter des questionnaires | raccourci de développement retiré, règles normales appliquées | client |
| Navigation étudiante | un seul menu, le même sur chaque page, assemblé depuis le `studentMenu` des modules actifs (`shared/helpers/menuEtudiant.js`) ; l'étudiant arrive sur l'Accueil sans passer par le portail ; Portail, Applications et Messages retirés de sa barre haute, Messages rejoindra le centre de notifications. Rien ne change pour le personnel | nous |
| Document et enseignement (B6) | un document se rattache à **un seul** `ScolEnseignement`, matière ou SAÉ, facultatif ; le lien s'efface si l'enseignement est supprimé. Un support commun à plusieurs matières se dépose dans chacune. Question 3 toujours sans réponse | nous |
| Notifications et messages | un seul fil pour l'étudiant : notes publiées, absences, documents de ses matières, actualités et messages. Les notifications sont recalculées, seule leur lecture est stockée. Les « messages » sont les copies des e-mails que l'intranet envoie (écoute du mailer) : la boîte universitaire n'est pas lue. La décision sur un justificatif n'a pas de date : son état figure dans le texte de l'absence | nous, validé par LCS |
| Favoris de documents (B7) | propres à chaque utilisateur (table `document_favori`), lus et modifiés par `/api/me/documents-favoris`. L'ancien champ `Document::isFavorite`, partagé par tous, est supprimé : un étudiant qui marquait un favori le marquait pour tout le monde | nous, validé par LCS |
| Couleur primaire (A6) | une seule primaire pour tous les modules : le violet de la DA `#4D3677`, avec une palette dérivée en OKLab (`VIOLET_IUT`, `packages/shell/assets/main.js`). Le jaune `#F7B000`, primaire d'origine de la DA, devient l'accent (`--accent-color`), toujours avec du texte `#4D3677`. La couleur par module (`primaryColor`) est supprimée | LCS |
| Barre latérale | façon GitHub : chaque entrée montre son icône puis son libellé ; repliée, la barre devient une colonne d'icônes, nommées par une infobulle au survol comme au focus, les libellés restant lus par les lecteurs d'écran. Le repli se fait depuis un bouton en bas de la barre, avec une icône de panneau latéral ; le ☰ de la barre haute ne sert plus qu'en mobile, pour ouvrir le tiroir. Icônes, bouton de repli et logo sont centrés sur un même axe. Le nom du module (« INTRANET ») est retiré de la barre haute de l'étudiant. La barre latérale est partagée : elle change aussi pour le personnel | nous |

## Questions en attente du client

À envoyer à Dannebicque.

1. **Emploi du temps** : qui branchera la vraie base Celcat, et quand ? Il faut l'accès réseau
   et les identifiants de la DSI. Le client laisse Cyndel préciser.
2. `packages` renvoie `documents`, le catalogue `document`, la fiche étudiant `UniTranet`.
   Lequel fait foi ?
3. Peut-on relier un document à une matière ou à une SAE ? C'est le cœur de la priorité 2.
4. Quelle est l'adresse de la messagerie universitaire des étudiants ? Le menu du profil doit y
   mener, on ne l'invente pas.
5. Les modules d'un étudiant sont codés en dur (`intranet`, `documents`, `SecurityController`) :
   doivent-ils suivre ceux de son département, pour lui ouvrir Stages et Assistance ?

Trois failles à leur signaler, car elles sont dans leur code de production :

- la déconnexion qui ne déconnectait pas, corrigée chez nous ;
- le lien d'abonnement iCal de l'intranet V3 : son identifiant est `MD5(prenom.nom)`, donc
  n'importe qui peut obtenir l'emploi du temps d'un étudiant en connaissant son nom. Ne pas le
  tester sur le vrai site ;
- les questionnaires, ouverts en modification à tout utilisateur connecté, corrigé chez nous (#36).

---

## Pièges connus

- **Vite recharge la page quand il découvre une dépendance.** Les composants PrimeVue sont
  importés à la compilation (`unplugin-vue-components`) : sans pré-optimisation, la première page
  qui en utilise un nouveau fait recharger tout le front et perd la navigation en cours. CI-Cypress
  a échoué ainsi de #55 à #68. `shared/vite.config.base.js` les pré-optimise tous ; pour
  reproduire la CI : `npx vite --port 3100 --force` dans `packages/shell`, puis
  `CYPRESS_BASE_URL=http://localhost:3100 npx cypress run`.
- **`components.d.ts`** est régénéré par Vite et bloque les changements de branche :
  `git checkout -- .` puis recommencer.
- **Deux `AuthController`**, dans `back/src` et dans `auth-bundle`, au code identique. Une
  correction faite dans l'un doit l'être dans l'autre.
- **`failOnDeprecation`** est activé dans PHPUnit : une dépréciation dans leur code fait échouer
  tous les tests.
- **Leur `Makefile`** cherche `vendor/bin/phpunit` : sans PHPUnit installé, la CI échoue dès
  qu'un dossier `tests/` existe.
- **Chercher un appel par nom de méthode, jamais par nom de variable.** Et ne pas tronquer une
  liste avec `head` quand on veut prouver qu'une chose n'existe pas : deux erreurs faites ainsi.
- Un dépôt écrit `use findAllByIdArrayTrait` avec un « f » minuscule : PHP l'accepte, une
  recherche exacte le rate.
- Les identifiants changent à chaque rechargement des fixtures : tout retrouver par code.
- Le code de l'intranet V3 est public (`Dannebicque/intranetV3`). C'est la référence pour porter
  une logique existante, comme cela a été fait pour Celcat.
- **Après un rechargement des fixtures**, relancer la synchro Celcat puis se déconnecter et se
  reconnecter : l'année universitaire mémorisée par le navigateur n'existe plus.
  `php bin/console doctrine:fixtures:load -n && php bin/console app:celcat:fausse-base && php bin/console app:celcat:sync`
- **Les fixtures du bundle stage** créent leurs propres années `2023-2024`, `2024-2025` et
  `2025-2026`, avec un tiret. Elles s'ajoutent aux années calculées et font doublon dans le
  sélecteur.
- **`php -S` n'expose pas l'environnement du processus** à Symfony sans
  `-d variables_order=EGPCS` : l'API retombe alors sur les `.env`, et en local sur
  `back/.env.local`, alors que la console voit bien les variables. Piège rencontré sur CI-Cypress.
- **Tester l'API sans mot de passe** : générer un jeton en console, puis l'envoyer en
  `Authorization: Bearer`.
  `php bin/console lexik:jwt:generate-token -c 'App\Entity\Users\Etudiant' etudiant`
- **`pnpm build` dans `intranet-bundle`** réécrit des fichiers suivis dans
  `src/Resources/public`. Ne pas le lancer pour vérifier une modification.
- **Aucune configuration ESLint** dans le dépôt, alors que `CLAUDE.md` en prévoit une : le lint
  front ne peut pas tourner.
- **Deux relations de groupes** : `Etudiant::groupes` et `EtudiantScolariteSemestre::groupes`.
  L'emploi du temps et les filtres de scolarité lisent la seconde.
