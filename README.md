# Agence 2

Dépôt de l'agence. On ne repart pas de zéro : la base de travail est le projet du client,
[IUTTroyes/uniServices](https://github.com/IUTTroyes/uniServices), reprise dans `uniservices/` et
amenée à évoluer.

```
.
├── uniservices/   la base reprise : API Symfony 7 + API Platform, front Vue 3 + Vite
├── docs/          contexte, audit de l'existant, questions client
└── CLAUDE.md      règles de travail : stack, GitFlow, conventions, accessibilité
```

Le site vitrine de l'agence est un projet distinct, en Next.js, en ligne sur
<https://figmium.vercel.app>.

Les données sont celles des fixtures, jamais celles de la production. On ne modifie ni
`intranetV3`, ni le dépôt uniServices d'origine.

## Prérequis

| Outil | Version | Pour |
|---|---|---|
| Node | 20 ou plus | le front |
| pnpm | 10.9 ou plus | le front |
| PHP | 8.2 ou plus | l'API |
| Composer | 2 | l'API |
| CLI Symfony | 5 ou plus | le serveur de développement de l'API |
| Docker | en marche | base MariaDB et serveur de mail |

Si tu n'as pas pnpm :

```bash
corepack enable && corepack prepare pnpm@10.9.0 --activate
```

## Installation

```bash
git clone git@github.com:Dannebicque/wra505D_agence-2.git && cd wra505D_agence-2/uniservices
```

Crée `.env.local` à la racine de `uniservices/` :

```bash
MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=uniservices
MYSQL_USER=uniservices
MYSQL_PASSWORD=uniservices
WEB_PORT=8080
DB_PORT=3307
PHPMYADMIN_PORT=8081
MAILDEV_PORT=1080
VITE_BASE_URL="http://127.0.0.1:8000"
VITE_API_URL="http://127.0.0.1:8000"
```

et `uniservices/back/.env.local` :

```bash
DATABASE_URL="mysql://uniservices:uniservices@127.0.0.1:3307/uniservices?serverVersion=10.8.0-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://127.0.0.1:1025
```

Ces deux fichiers ne sont pas versionnés, chacun les crée chez soi. Puis :

```bash
docker compose -f docker/docker-compose.yml --env-file .env.local up -d db maildev && pnpm install
cd back && composer install && php bin/console lexik:jwt:generate-keypair --skip-if-exists && php bin/console doctrine:schema:create && php bin/console doctrine:fixtures:load --no-interaction
```

Il n'y a pas de dossier `migrations/` : le schéma se crée depuis les entités. Les clés JWT et
`vendor/` ne sont pas versionnés non plus, d'où les deux commandes ci-dessus.

## Lancer

| Commande | Depuis | Adresse |
|---|---|---|
| `symfony server:start -d --port=8000` | `uniservices/back` | <http://127.0.0.1:8000/api> |
| `pnpm run dev` | `uniservices` | <http://localhost:3000/app/> |

Comptes chargés par les fixtures, mot de passe `test` : `etudiant`, `personnel`, `superadmin`.
Ils se saisissent dans le bloc « Compte invité », pas via « Connexion URCA » qui pointe vers le
CAS de l'université. Le mot de passe doit être tapé au clavier : PrimeVue ignore une valeur
injectée et le bouton reste inactif.

## L'emploi du temps : le branchement Celcat

L'emploi du temps officiel vit dans **Celcat**, l'outil de planning de l'université. uniServices
ne le recopiait jusqu'ici que depuis l'intranet actuel. Il sait maintenant le lire directement,
par la commande `app:celcat:sync`, qui reprend les requêtes et les règles de l'intranet V3.

En production, Celcat est une base SQL Server accessible uniquement depuis le réseau de
l'université, avec des identifiants fournis par la DSI. Tant qu'on n'y a pas accès, on
développe contre une **fausse base Celcat** : un fichier SQLite avec les mêmes tables, rempli
d'une semaine type de BUT 1 MMI. Le code et les requêtes sont exactement ceux qui tourneront
en production, seule l'adresse de la base change.

```bash
cd uniservices/back && php bin/console app:celcat:fausse-base
```

Ajoute ensuite dans `uniservices/back/.env.local` :

```bash
CELCAT_DSN="sqlite:%kernel.project_dir%/var/celcat/fausse-base.sqlite"
```

puis synchronise :

```bash
cd uniservices/back && php bin/console app:celcat:sync
```

La commande affiche, par département, les créneaux créés, mis à jour et supprimés, ainsi que
les codes Celcat — groupes, enseignants, matières — qui n'ont trouvé aucune correspondance
dans uniServices. Le jour du vrai branchement, c'est la liste de ce qu'il faudra renseigner.

Relancer la commande ne duplique rien : les créneaux existants sont mis à jour. Un cours retiré
de Celcat est supprimé, sauf s'il porte des absences : il est alors conservé et signalé, car la
base refuse de supprimer un créneau qui en porte.

Pour brancher la vraie base, il suffira de renseigner `CELCAT_DSN`, `CELCAT_USER` et
`CELCAT_PASSWORD` avec les accès de la DSI, par exemple
`CELCAT_DSN="dblib:host=serveur-celcat;dbname=celcat"`.

## Contrôles avant de pousser

Depuis `uniservices/`, leur `Makefile` rejoue les validations de leur CI :

```bash
make check
```

Il enchaîne, côté back, `composer validate`, `lint:container`, `doctrine:schema:validate`,
PHPStan et les tests PHPUnit ; côté front, l'installation, les tests et le build.

Une tâche n'est terminée que si tout passe.

## Ce qu'il faut savoir avant de travailler dedans

- **Notre périmètre est la partie étudiante.** Les écrans enseignant et administratif ne nous
  regardent pas, même s'ils sont dans le même dépôt.
- **Les appels à l'API passent par `shared/requests/`**, via `apiService.js` et `apiCall.js`.
  Un composant n'appelle jamais axios directement.
- **`components.d.ts` est régénéré par Vite** et peut bloquer un changement de branche. Un
  `git checkout -- .` suffit.
- **L'OpenAPI complet de l'API** s'exporte avec `cd back && php bin/console api:openapi:export`,
  utile pour connaître la forme exacte d'une réponse avant d'écrire un écran.
