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

### En attente de merge : `feature/celcat-connector`

**À merger en premier.** Vérifier : `git log origin/develop..origin/feature/celcat-connector`.

Elle contient le branchement Celcat complet, développé contre une fausse base :

- `CelcatReader` exécute les requêtes de l'intranet V3 via PDO. Seule l'adresse de la base
  change entre développement (SQLite) et production (SQL Server).
- `CelcatEventConverter` déplie le masque de semaines `YYNY…` en créneaux datés.
- `CelcatSynchronizer` met à jour sans recréer, et **conserve un créneau qui porte des absences**
  (la base refuse de le supprimer : `ON DELETE RESTRICT`). Il remplit aussi `StructureCalendrier`,
  sans quoi l'écran étudiant ne sait pas quelle semaine afficher.
- PHPUnit ajouté au back, qui n'avait aucun outil de test : 26 tests.
- Correction de `UuidTrait` (dépréciation PHP 8.4 qui faisait échouer les tests).

### État de la CI

| Job | État | Pourquoi |
|---|---|---|
| CI-Packages (front) | vert | |
| CI-Back | rouge | PHPStan : 252 erreurs sur `develop`, 244 une fois Celcat mergé |
| CI-Cypress | rouge | pas encore étudié |

---

## Ce qu'il reste à faire, dans l'ordre

1. **Merger `feature/celcat-connector`.**
2. **Inscrire l'étudiant de test dans des groupes.** Il n'en a aucun, or l'écran d'emploi du
   temps ne montre que les cours des groupes de l'étudiant. L'inscrire en S1, dans `MMICM`,
   `MMITDAB` et `MMITPA`, via les fixtures.
3. **Passe visuelle de l'emploi du temps**, fiche C3 et priorité 4. Écran concerné :
   `packages/intranet-bundle/assets/components/Edt/EdtEtudiant.vue`.
4. **E4, les erreurs PHPStan.** Décision prise : **pas de baseline, on corrige tout**. Commencer
   par les appels à des méthodes inexistantes, qui sont de vrais plantages. Quatre bugs attendent
   une décision du client, voir plus bas.
5. **Étudier l'échec de CI-Cypress.**
6. **Examiner les 18 alertes de sécurité** remontées par `composer audit`, antérieures à nous.
7. Le reste du backlog, colonnes A à D et propositions P.

## Questions en attente du client

À envoyer à Dannebicque.

1. **Convention de stage** : générer le PDF plante, le nombre d'ECTS n'est stocké nulle part.
2. **Création manuelle d'un étudiant** : `addAnnee()` n'existe pas, l'année se déduit des
   semestres. L'inscrire aux deux semestres de l'année, ou au semestre en cours ?
3. **Emploi du temps** : qui branchera la vraie base Celcat, et quand ? Il faut l'accès réseau
   et les identifiants de la DSI.
4. **Moyennes** : l'import depuis l'intranet V3 écrit dans un format que leur refonte a abandonné.
5. `packages` renvoie `documents`, le catalogue `document`, la fiche étudiant `UniTranet`.
   Lequel fait foi ?
6. Peut-on relier un document à une matière ou à une SAE ? C'est le cœur de la priorité 2.

Deux failles à leur signaler, car elles sont dans leur code de production :

- la déconnexion qui ne déconnectait pas, corrigée chez nous ;
- le lien d'abonnement iCal de l'intranet V3 : son identifiant est `MD5(prenom.nom)`, donc
  n'importe qui peut obtenir l'emploi du temps d'un étudiant en connaissant son nom. Ne pas le
  tester sur le vrai site.

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
