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
- **Claude ne peut pas merger.** `gh` n'est pas installé, et le ruleset du dépôt exige une PR et
  une approbation. On pousse la branche, on donne l'URL de comparaison, et l'utilisateur merge.
- Après un merge annoncé, vérifier qu'il a bien eu lieu, puis supprimer la branche des deux
  côtés :
  `git merge-base --is-ancestor origin/<branche> origin/develop`.
- Corriger un bug dans le code back du client est permis, même hors du périmètre étudiant. On n'y
  ajoute pas de fonctionnalité.

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
| CI-Back | rouge | PHPStan : 19 erreurs, toutes dans `QuestionnaireVoter`, en attente de la question 7 |
| CI-Cypress | rouge | pas encore étudié |

---

## Répartition dans l'équipe

Suivie dans le tableau de tâches de l'équipe, hors de ce dépôt.

| Qui | Colonne du backlog |
|---|---|
| LCS | E, back et CI |
| LOU | A, accessibilité transverse, branche `feature/accessibilite` |
| JEREMY | C, tableau de bord et emploi du temps |

Les colonnes B (documents) et D (recherche) n'ont encore personne. Ce sont pourtant les
priorités 1 et 2 du client. D1 est du back.

---

## Ce qu'il reste à faire, dans l'ordre

1. **Envoyer les questions au client**, plus bas. Elles bloquent la fin d'E6 et la fiche B6.
2. **Revenir à l'année active quand l'année mémorisée n'existe plus.** Après un rechargement des
   fixtures, `initAnneeUnivData()` (`shared/requests/initializeData.js`) garde un identifiant
   d'année disparu, et l'agenda reste vide. Petit correctif front.
3. **Passe visuelle de l'emploi du temps**, fiche C3 et priorité 4, côté JEREMY. Écran concerné :
   `packages/intranet-bundle/assets/components/Edt/EdtEtudiant.vue`.
4. **Étudier l'échec de CI-Cypress.**
5. **Examiner les 18 alertes de sécurité** remontées par `composer audit`, antérieures à nous.
6. **D1, l'endpoint de recherche tolérant aux fautes**, à cadrer avant de coder.
7. Le reste du backlog, colonnes A à D et propositions P.

## Questions en attente du client

À envoyer à Dannebicque. Les numéros 1, 2 et 4 sont cités dans `phpstan.neon` : ne pas les
renuméroter.

1. **Convention de stage** : `StagePdfController` appelle `StagePeriode::getNbEcts()`, qui
   n'existe pas, et le nombre d'ECTS n'est stocké nulle part. Le stocker sur la période, le
   déduire des UE, ou retirer la mention du PDF ?
2. **Création manuelle d'un étudiant** : `addAnnee()` n'existe pas, l'année se déduit des
   semestres. L'inscrire aux deux semestres de l'année, ou au semestre en cours ?
3. **Emploi du temps** : qui branchera la vraie base Celcat, et quand ? Il faut l'accès réseau
   et les identifiants de la DSI.
4. **Moyennes** : les accesseurs `moyennesMatiere` et `moyennesUe` pointent des propriétés
   retirées, mais l'import depuis l'intranet V3 écrit toujours dedans. Rétablir les colonnes, ou
   cesser de les écrire ?
5. `packages` renvoie `documents`, le catalogue `document`, la fiche étudiant `UniTranet`.
   Lequel fait foi ?
6. Peut-on relier un document à une matière ou à une SAE ? C'est le cœur de la priorité 2.
7. **Droits sur les questionnaires** : `QuestionnaireVoter` renvoie `true` avant ses règles, avec
   le commentaire « pour les tests ». Peut-on appliquer les règles écrites en dessous ? Elles
   passent des noms de rôles (`ROLE_ADMIN`…) à `hasAnyPermission()` : est-ce le format attendu ?

Trois failles à leur signaler, car elles sont dans leur code de production :

- la déconnexion qui ne déconnectait pas, corrigée chez nous ;
- le lien d'abonnement iCal de l'intranet V3 : son identifiant est `MD5(prenom.nom)`, donc
  n'importe qui peut obtenir l'emploi du temps d'un étudiant en connaissant son nom. Ne pas le
  tester sur le vrai site ;
- les questionnaires, ouverts en modification à tout utilisateur connecté (question 7).

---

## Pièges connus

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
- **Tester l'API sans mot de passe** : générer un jeton en console, puis l'envoyer en
  `Authorization: Bearer`.
  `php bin/console lexik:jwt:generate-token -c 'App\Entity\Users\Etudiant' etudiant`
- **`pnpm build` dans `intranet-bundle`** réécrit des fichiers suivis dans
  `src/Resources/public`. Ne pas le lancer pour vérifier une modification.
- **Aucune configuration ESLint** dans le dépôt, alors que `CLAUDE.md` en prévoit une : le lint
  front ne peut pas tourner.
- **Deux relations de groupes** : `Etudiant::groupes` et `EtudiantScolariteSemestre::groupes`.
  L'emploi du temps et les filtres de scolarité lisent la seconde.
