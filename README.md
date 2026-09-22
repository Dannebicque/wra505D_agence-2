# Agence 2

Dépôt de l'agence. Deux volets, un seul dépôt :

- **volet 1** — site vitrine de l'agence. Pas encore démarré.
- **volet 2** — espace étudiant de l'intranet de l'IUT de Troyes. C'est ce que contient ce dépôt
  aujourd'hui.

Les règles de travail sont dans [`CLAUDE.md`](CLAUDE.md) : stack, GitFlow, conventions de code,
accessibilité, définition de terminé. Lis-le avant ta première tâche, il n'est pas répété ici.

## Installer le projet

### Prérequis

| Outil | Version | Pourquoi |
|---|---|---|
| Node | 20 ou plus | testé sur 23.8.0 |
| pnpm | 10.9 ou plus | gestionnaire de paquets du projet |

`npm install` échoue sur ce projet : npm 11.1.0 plante en résolvant les dépendances de pair de
`@nuxt/test-utils` (`Cannot read properties of null (reading 'edgesOut')`). Le projet utilise donc
pnpm, épinglé dans `package.json`. Si tu n'as pas pnpm :

```bash
corepack enable && corepack prepare pnpm@10.9.0 --activate
```

### Installation

```bash
git clone git@github.com:Dannebicque/wra505D_agence-2.git && cd wra505D_agence-2 && pnpm install
```

`pnpm install` déclenche `nuxt prepare`, qui génère les types dans `.nuxt/`. Si ton éditeur signale
des imports inconnus, c'est que cette étape n'a pas tourné : relance `pnpm install`.

### Lancer

```bash
pnpm dev
```

L'application écoute sur <http://localhost:3000>. Les points d'entrée de l'API répondent
immédiatement, par exemple <http://localhost:3000/api/documents>.

### Commandes

| Commande | Effet |
|---|---|
| `pnpm dev` | serveur de développement |
| `pnpm build` | build de production |
| `pnpm lint` / `pnpm lint:fix` | ESLint |
| `pnpm typecheck` | vérification TypeScript |
| `pnpm test` | tests unitaires Vitest |
| `pnpm test:e2e` | tests Cypress, application à lancer avant |

Une tâche n'est terminée que si `lint`, `typecheck`, `test` et `build` passent tous les quatre.

## Comment les données arrivent

Aucun composant n'appelle `$fetch`. Tout passe par `app/composables/useApi.ts`, couche d'accès
unique. Le jour où la vraie API remplace les données simulées, c'est le seul fichier à changer.

Aujourd'hui les requêtes partent sur le serveur Nitro local (`server/api/`), qui rejoue le contrat
de l'API réelle du client, **uniServices** : mêmes chemins, même enveloppe Hydra d'API Platform,
même authentification par cookie httpOnly.

Règle à respecter en ajoutant un point d'entrée simulé : **le mock reste un sous-ensemble strict du
contrat réel.** Un champ absent chez nous mais présent chez eux ne cassera rien à la bascule ;
l'inverse casserait. Ne sers jamais un champ que tu n'as pas vu dans leur schéma OpenAPI.

### Basculer sur la vraie API

```bash
NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000 pnpm dev
```

Les appels partent alors sur le backend Symfony, sans qu'une ligne d'appelant change.

## Lancer l'API réelle en local

Facultatif. Utile pour vérifier un format de réponse avant d'écrire un mock, ou pour travailler
contre de vraies données. Le dépôt du client est
[IUTTroyes/uniServices](https://github.com/IUTTroyes/uniServices). On le clone **à côté** du nôtre,
on n'y pousse rien et on ne le modifie pas.

Prérequis supplémentaires : PHP 8.2 ou plus, Composer, la CLI Symfony, Docker.

```bash
git clone https://github.com/IUTTroyes/uniServices.git && cd uniServices && pnpm install
```

Crée `.env.local` à la racine du dépôt cloné :

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

et `back/.env.local` :

```bash
DATABASE_URL="mysql://uniservices:uniservices@127.0.0.1:3307/uniservices?serverVersion=10.8.0-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://127.0.0.1:1025
```

Puis, dans l'ordre :

```bash
docker compose -f docker/docker-compose.yml --env-file .env.local up -d db maildev
cd back && composer install && php bin/console lexik:jwt:generate-keypair && php bin/console doctrine:schema:create && php bin/console doctrine:fixtures:load --no-interaction && symfony server:start -d --port=8000
```

Il n'y a pas de dossier `migrations/` dans leur dépôt : le schéma se crée depuis les entités.

L'API répond sur <http://127.0.0.1:8000/api>, leur interface sur <http://localhost:3000/app/> après
un `pnpm run dev` à la racine de leur dépôt. Attention, ce port est le même que le nôtre : ne lance
pas les deux en même temps sans en déplacer un.

Comptes chargés par les fixtures, mot de passe `test` pour les trois : `etudiant`, `personnel`,
`superadmin`. Ils se saisissent dans le bloc « Compte invité », pas via « Connexion URCA » qui
pointe vers le CAS de l'université.

## Périmètre

On construit à côté de l'intranet réel. On ne modifie ni `intranetV3`, ni `uniServices`, ni aucune
donnée de production.
